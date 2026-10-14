<?php

namespace App\Services\Commercial;

use App\Enums\PlanAudience;
use App\Enums\PlanPeriod;
use App\Enums\SubscriptionStatus as S;
use App\Exceptions\CommercialRuleException;
use App\Models\Agency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Engagement\NotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Souscriptions (COM-03 à COM-07). Chaque transition passe par ce service : vérifie qu'elle est autorisée,
 * l'inscrit dans audit_logs avec son auteur, et ne donne JAMAIS un droit sans encaissement confirmé.
 */
class SubscriptionService
{
    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly PaymentService $payments,
        private readonly QuotaEnforcer $quota,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /** Souscripteur d'un utilisateur : son agence s'il en est le gérant, sinon lui-même s'il est particulier. */
    public function subscriberFor(User $actor): User|Agency
    {
        $member = $actor->activeMembership();
        if ($member !== null) {
            if (! $member->isManager()) {
                throw new CommercialRuleException('forbidden', 'Seul le gérant peut gérer l\'abonnement de l\'agence.', 403);
            }

            return $member->agency;
        }
        if (! $actor->hasRole('particulier')) {
            throw new CommercialRuleException('forbidden', 'Action réservée aux particuliers et aux gérants d\'agence.', 403);
        }

        return $actor;
    }

    /** Demande de formule : en attente, avec une référence de paiement, sans aucun avantage (COM-03). */
    public function request(User $actor, Plan $plan): Subscription
    {
        $subscriber = $this->subscriberFor($actor);
        $this->assertOffered($plan, $subscriber);
        [$type, $id] = $this->entitlements->ref($subscriber);

        try {
            return DB::transaction(function () use ($actor, $plan, $type, $id) {
                $next = DB::selectOne("select nextval('payment_reference_seq') as n")->n;
                $subscription = new Subscription([
                    'subscriber_type' => $type, 'subscriber_id' => $id, 'plan_id' => $plan->id, 'requested_by' => $actor->id,
                    'payment_reference' => sprintf('PAY-%d-%06d', now()->year, $next),
                    // Prix, quotas et fonctionnalités figés : modifier le catalogue ne change pas cette souscription.
                    'plan_snapshot' => ['code' => $plan->code, 'name' => $plan->name, 'price_xof' => $plan->price_xof, 'period' => $plan->period->value, 'limits' => $plan->limits, 'features' => $plan->features],
                ]);
                $subscription->forceFill(['status' => S::Pending])->save();
                $this->audit->record('subscription.requested', $subscription, null, ['plan' => $plan->code, 'price_xof' => $plan->price_xof], $actor->id);
                $this->notifyAdmins('subscription.requested', ['subscription' => $subscription->ulid]);

                return $subscription;
            });
        } catch (UniqueConstraintViolationException) {
            throw new CommercialRuleException('request_pending', 'Une demande est déjà en attente : annulez-la avant d\'en faire une autre.');
        }
    }

    /** Annulation d'une demande en attente, par le demandeur ou un administrateur (motif enregistré). */
    public function cancelPending(Subscription $subscription, User $actor, string $reason): Subscription
    {
        $this->assertStatus($subscription, [S::Pending]);
        $subscription->forceFill(['status' => S::Cancelled, 'reason' => $reason])->save();
        $this->audit->record('subscription.cancelled', $subscription, ['status' => 'pending'], ['status' => 'cancelled', 'reason' => $reason], $actor->id);

        return $subscription;
    }

    /**
     * Activation par un administrateur. Exige un encaissement CONFIRMÉ couvrant le prix figé (COM-04).
     * Une souscription active existante est remplacée (la nouvelle prend sa place à la date d'activation).
     */
    public function activate(Subscription $subscription, User $admin): Subscription
    {
        return DB::transaction(function () use ($subscription, $admin) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, [S::Pending]);

            $price = (int) $locked->plan_snapshot['price_xof'];
            if ($this->payments->settlementStatus($locked) !== 'confirmed' || $this->payments->confirmedAmount($locked) < $price) {
                throw new CommercialRuleException('payment_required', 'Un encaissement confirmé couvrant le prix de la formule est requis avant activation.');
            }

            $subscriber = $this->subscriber($locked);
            $previous = Subscription::where('subscriber_type', $locked->subscriber_type)->where('subscriber_id', $locked->subscriber_id)
                ->where('status', S::Active->value)->lockForUpdate()->first();

            // L'ancienne souscription est remplacée AVANT l'activation de la nouvelle : la base n'autorise qu'une active à la fois.
            if ($previous !== null) {
                $previous->forceFill(['status' => S::Replaced, 'ends_at' => now(), 'replaced_by_id' => $locked->id])->save();
                $this->audit->record('subscription.replaced', $previous, ['status' => 'active'], ['status' => 'replaced', 'by' => $locked->ulid], $admin->id);
            }
            $period = PlanPeriod::from($locked->plan_snapshot['period']);
            $locked->forceFill([
                'status' => S::Active, 'activated_by' => $admin->id, 'starts_at' => now(),
                'ends_at' => match ($period) {
                    PlanPeriod::Monthly => now()->addMonth(), PlanPeriod::Quarterly => now()->addMonths(3), PlanPeriod::Annual => now()->addYear(),
                },
            ])->save();
            $this->entitlements->forget($subscriber);
            $this->audit->record('subscription.activated', $locked, ['status' => 'pending'], ['status' => 'active', 'plan' => $locked->plan_snapshot['code'], 'ends_at' => $locked->ends_at?->toIso8601String()], $admin->id);

            // Une formule moins généreuse que la précédente laisse un délai pour choisir les annonces à garder.
            if ($this->quota->activeCount($subscriber) > $this->entitlements->limit($subscriber, 'active_listings')) {
                $locked->forceFill(['downgrade_deadline_at' => now()->addDays(config('chezvous.commercial.downgrade_grace_days'))])->save();
            }
            $this->notifyRequester($locked, 'subscription.activated', ['subscription' => $locked->ulid, 'plan' => $locked->plan_snapshot['name'], 'ends_at' => $locked->ends_at?->toIso8601String()]);

            return $locked;
        });
    }

    /** Suspension : avantages retirés IMMÉDIATEMENT, annonces excédentaires mises en pause, motif obligatoire. */
    public function suspend(Subscription $subscription, User $admin, string $reason): Subscription
    {
        return DB::transaction(function () use ($subscription, $admin, $reason) {
            $this->assertStatus($subscription, [S::Active]);
            $subscription->forceFill(['status' => S::Suspended, 'reason' => $reason])->save();
            $subscriber = $this->subscriber($subscription);
            $this->entitlements->forget($subscriber);
            $paused = $this->quota->enforce($subscriber);
            $this->audit->record('subscription.suspended', $subscription, ['status' => 'active'], ['status' => 'suspended', 'reason' => $reason, 'listings_paused' => $paused], $admin->id);
            $this->notifyRequester($subscription, 'subscription.suspended', ['subscription' => $subscription->ulid, 'listings_paused' => $paused]);

            return $subscription;
        });
    }

    /** Réintégration : la date de fin n'est PAS prolongée automatiquement. */
    public function reinstate(Subscription $subscription, User $admin): Subscription
    {
        $this->assertStatus($subscription, [S::Suspended]);
        if ($subscription->ends_at === null || $subscription->ends_at->isPast()) {
            throw new CommercialRuleException('subscription_expired', 'La date de fin est dépassée : une nouvelle souscription est nécessaire.');
        }
        $subscription->forceFill(['status' => S::Active, 'reason' => null])->save();
        $this->entitlements->forget($this->subscriber($subscription));
        $this->audit->record('subscription.reinstated', $subscription, ['status' => 'suspended'], ['status' => 'active'], $admin->id);

        return $subscription;
    }

    /**
     * Tâche quotidienne : expire les souscriptions échues. Les droits retombent au niveau gratuit ; un délai est laissé
     * pour choisir les annonces à garder avant la mise en pause automatique des plus anciennes.
     *
     * @return int nombre de souscriptions expirées
     */
    public function expireDue(): int
    {
        $count = 0;
        Subscription::whereIn('status', [S::Active->value, S::Suspended->value])->where('ends_at', '<=', now())->each(function (Subscription $s) use (&$count) {
            $wasActive = $s->status === S::Active;
            $s->forceFill(['status' => S::Expired] + ($wasActive ? ['downgrade_deadline_at' => now()->addDays(config('chezvous.commercial.downgrade_grace_days'))] : []))->save();
            $this->entitlements->forget($this->subscriber($s));
            $this->audit->record('subscription.expired', $s, ['status' => $wasActive ? 'active' : 'suspended'], ['status' => 'expired'], null);
            $this->notifyRequester($s, 'subscription.expired', ['subscription' => $s->ulid, 'grace_until' => $s->downgrade_deadline_at?->toIso8601String()]);
            $count++;
        });

        return $count;
    }

    /**
     * Rappels d'expiration à 7 jours et 1 jour, une seule fois chacun.
     *
     * @return int nombre de rappels envoyés
     */
    public function remindExpiring(): int
    {
        $sent = 0;
        // Le rappel court d'abord : une souscription qui expire dans moins d'un jour ne reçoit pas aussi celui de 7 jours.
        foreach ([1 => 'reminded_1d_at', 7 => 'reminded_7d_at'] as $days => $column) {
            Subscription::where('status', S::Active->value)->whereNull($column)->where('ends_at', '>', now())->where('ends_at', '<=', now()->addDays($days))
                ->each(function (Subscription $s) use ($days, $column, &$sent) {
                    // Réservation atomique : la tâche peut tourner deux fois sans doubler le rappel.
                    if (Subscription::whereKey($s->id)->whereNull($column)->update([$column => now()]) === 1) {
                        // Le rappel de 7 jours est considéré comme rendu si la souscription expire déjà dans moins d'un jour.
                        if ($days === 1) {
                            Subscription::whereKey($s->id)->whereNull('reminded_7d_at')->update(['reminded_7d_at' => now()]);
                        }
                        $this->notifyRequester($s, 'subscription.expiring', ['subscription' => $s->ulid, 'days' => $days, 'ends_at' => $s->ends_at?->toIso8601String()]);
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    /**
     * Applique le quota quand le délai de grâce est écoulé. Renvoie le nombre d'annonces mises en pause.
     */
    public function enforceDowngrades(): int
    {
        $paused = 0;
        Subscription::whereNotNull('downgrade_deadline_at')->where('downgrade_deadline_at', '<=', now())->each(function (Subscription $s) use (&$paused) {
            $n = $this->quota->enforce($this->subscriber($s));
            $s->forceFill(['downgrade_deadline_at' => null])->save();
            if ($n > 0) {
                $this->audit->record('subscription.downgrade_enforced', $s, null, ['listings_paused' => $n], null);
                $this->notifyRequester($s, 'subscription.downgrade_enforced', ['subscription' => $s->ulid, 'listings_paused' => $n]);
            }
            $paused += $n;
        });

        return $paused;
    }

    public function subscriber(Subscription $s): User|Agency
    {
        return $s->subscriber_type === 'agency' ? Agency::findOrFail($s->subscriber_id) : User::findOrFail($s->subscriber_id);
    }

    private function assertOffered(Plan $plan, User|Agency $subscriber): void
    {
        $audience = $subscriber instanceof Agency ? PlanAudience::Agency : PlanAudience::Individual;
        if ($plan->is_free || ! $plan->is_active || ! $plan->isConfigured()) {
            throw new CommercialRuleException('plan_unavailable', 'Cette formule n\'est pas proposée pour le moment.');
        }
        if ($plan->audience !== PlanAudience::Both && $plan->audience !== $audience) {
            throw new CommercialRuleException('plan_not_for_you', 'Cette formule ne correspond pas à votre type de compte.');
        }
    }

    /** @param list<S> $allowed */
    private function assertStatus(Subscription $subscription, array $allowed): void
    {
        if (! in_array($subscription->status, $allowed, true)) {
            throw new CommercialRuleException('invalid_transition', "Action impossible : la souscription est « {$subscription->status->value} ».");
        }
    }

    /** @param array<string, mixed> $data */
    private function notifyRequester(Subscription $s, string $type, array $data): void
    {
        $this->notifications->notify($s->requested_by, $type, $data);
    }

    /** @param array<string, mixed> $data */
    private function notifyAdmins(string $type, array $data): void
    {
        $this->notifications->notifyMany(User::role(['admin', 'super_admin'])->get(), $type, $data);
    }
}
