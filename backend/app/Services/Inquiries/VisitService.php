<?php

namespace App\Services\Inquiries;

use App\Enums\InquiryStatus;
use App\Enums\VisitStatus as V;
use App\Exceptions\InquiryRuleException;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\User;
use App\Models\Visit;
use App\Services\Audit\AuditLogger;
use App\Services\Engagement\NotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Visites (DEM-03). Proposition de 1 à 3 créneaux, confirmation par l'autre partie, report, annulation, réalisation.
 * Heures stockées en UTC (le Sénégal n'a pas de changement d'heure). Aucune double réservation d'un créneau.
 */
class VisitService
{
    public function __construct(
        private readonly InquiryAccess $access,
        private readonly InquiryStateService $states,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $slots
     */
    public function propose(Inquiry $inquiry, User $actor, array $slots): Visit
    {
        if (! $inquiry->isOpen()) {
            throw new InquiryRuleException('inquiry_closed', 'Cette demande est terminée.');
        }
        $normalized = $this->normalizeSlots($slots);

        try {
            return DB::transaction(function () use ($inquiry, $actor, $normalized) {
                $visit = new Visit(['inquiry_id' => $inquiry->id, 'listing_id' => $inquiry->listing_id, 'proposed_by' => $actor->id, 'proposed_slots' => $normalized]);
                $visit->forceFill(['status' => V::Proposed])->save();
                $this->audit->record('visit.proposed', $visit, null, ['slots' => count($normalized)], $actor->id);
                $this->notifyOther($inquiry, $actor, 'visit.proposed', $visit);

                return $visit;
            });
        } catch (UniqueConstraintViolationException) {
            throw new InquiryRuleException('visit_active', 'Une visite est déjà en cours pour cette demande.');
        }
    }

    /** Confirmation d'un créneau par la partie qui n'a PAS fait la dernière proposition. */
    public function confirm(Visit $visit, User $actor, string $slot): Visit
    {
        return DB::transaction(function () use ($visit, $actor, $slot) {
            [$visit, $inquiry] = $this->lock($visit);
            $this->assertStatus($visit, [V::Proposed, V::Rescheduled]);
            $this->assertOtherSide($visit, $inquiry, $actor);

            $chosen = $this->matchSlot($visit, $slot);
            if ($chosen->isPast()) {
                throw new InquiryRuleException('slot_past', 'Ce créneau est déjà passé.');
            }
            $this->assertNoConflict($visit, $inquiry, $chosen);

            $visit->forceFill([
                'status' => V::Confirmed, 'scheduled_at' => $chosen, 'confirmed_at' => now(),
                'reminder_24h_sent_at' => null, 'reminder_2h_sent_at' => null,
            ])->save();
            if ($inquiry->status === InquiryStatus::New) {
                $this->states->apply($inquiry, InquiryStatus::InProgress, $actor);
            }
            if ($inquiry->status === InquiryStatus::InProgress) {
                $this->states->apply($inquiry, InquiryStatus::VisitScheduled, $actor);
            }
            $this->audit->record('visit.confirmed', $visit, null, ['scheduled_at' => $chosen->toIso8601String()], $actor->id);
            $this->notifyOther($inquiry, $actor, 'visit.confirmed', $visit);

            return $visit;
        });
    }

    /**
     * Contre-proposition ou report : nouveaux créneaux soumis à l'autre partie.
     *
     * @param  list<string>  $slots
     */
    public function reschedule(Visit $visit, User $actor, array $slots): Visit
    {
        $normalized = $this->normalizeSlots($slots);

        return DB::transaction(function () use ($visit, $actor, $normalized) {
            [$visit, $inquiry] = $this->lock($visit);
            $this->assertStatus($visit, [V::Proposed, V::Rescheduled, V::Confirmed]);
            $this->assertParty($inquiry, $actor);
            if ($visit->status !== V::Confirmed) {
                $this->assertOtherSide($visit, $inquiry, $actor); // on ne se répond pas à soi-même
            }

            $wasConfirmed = $visit->status === V::Confirmed;
            $visit->forceFill([
                'status' => V::Rescheduled, 'proposed_slots' => $normalized, 'proposed_by' => $actor->id,
                'scheduled_at' => null, 'confirmed_at' => null, 'reminder_24h_sent_at' => null, 'reminder_2h_sent_at' => null,
            ])->save();
            if ($wasConfirmed && $inquiry->status === InquiryStatus::VisitScheduled) {
                $this->states->apply($inquiry, InquiryStatus::InProgress, $actor);
            }
            $this->audit->record('visit.rescheduled', $visit, null, ['slots' => count($normalized)], $actor->id);
            $this->notifyOther($inquiry, $actor, 'visit.rescheduled', $visit);

            return $visit;
        });
    }

    public function cancel(Visit $visit, User $actor, ?string $reason = null): Visit
    {
        return DB::transaction(function () use ($visit, $actor, $reason) {
            [$visit, $inquiry] = $this->lock($visit);
            $this->assertStatus($visit, [V::Proposed, V::Rescheduled, V::Confirmed]);
            $this->assertParty($inquiry, $actor);

            $visit->forceFill(['status' => V::Cancelled, 'cancel_reason' => $reason])->save();
            if ($inquiry->status === InquiryStatus::VisitScheduled) {
                $this->states->apply($inquiry, InquiryStatus::InProgress, $actor);
            }
            $this->audit->record('visit.cancelled', $visit, null, null, $actor->id);
            $this->notifyOther($inquiry, $actor, 'visit.cancelled', $visit);

            return $visit;
        });
    }

    /** « Effectuée » : côté annonceur uniquement, une fois l'heure passée. */
    public function markDone(Visit $visit, User $actor): Visit
    {
        return DB::transaction(function () use ($visit, $actor) {
            [$visit, $inquiry] = $this->lock($visit);
            $this->assertStatus($visit, [V::Confirmed]);
            if ($this->access->ownerSide($actor, $inquiry) !== InquiryAccess::RESPOND) {
                throw new InquiryRuleException('forbidden', 'Seul l\'annonceur peut marquer la visite comme effectuée.', 403);
            }
            if ($visit->scheduled_at === null || $visit->scheduled_at->isFuture()) {
                throw new InquiryRuleException('visit_not_started', 'La visite n\'a pas encore eu lieu.');
            }
            $visit->forceFill(['status' => V::Done])->save();
            $this->audit->record('visit.done', $visit, null, null, $actor->id);
            $this->notifications->notify($inquiry->requester_id, 'visit.done', ['inquiry' => $inquiry->ulid, 'visit' => $visit->ulid, 'listing' => $inquiry->listing->reference]);

            return $visit;
        });
    }

    /** Retour du demandeur après la visite : le bien est-il disponible et conforme ? */
    public function feedback(Visit $visit, User $actor, bool $available, bool $conform, ?string $comment): Visit
    {
        $inquiry = $visit->inquiry;
        if (! $this->access->isRequester($actor, $inquiry)) {
            throw new InquiryRuleException('forbidden', 'Seul le demandeur peut donner son retour.', 403);
        }
        if ($visit->status !== V::Done) {
            throw new InquiryRuleException('visit_not_done', 'Le retour est possible après une visite effectuée.');
        }
        if ($visit->feedback_available !== null) {
            throw new InquiryRuleException('feedback_done', 'Vous avez déjà donné votre retour.');
        }
        $visit->forceFill(['feedback_available' => $available, 'feedback_conform' => $conform, 'feedback_comment' => $comment])->save();
        $this->audit->record('visit.feedback', $visit, null, ['available' => $available, 'conform' => $conform], $actor->id);
        $this->notifications->notifyMany($this->access->ownerSideRecipients($inquiry), 'visit.feedback', ['inquiry' => $inquiry->ulid, 'visit' => $visit->ulid]);

        return $visit;
    }

    /**
     * @param  list<string>  $slots
     * @return list<string> créneaux triés, en UTC
     */
    public function normalizeSlots(array $slots): array
    {
        $cfg = config('chezvous.inquiries.visit');
        if (count($slots) < 1 || count($slots) > $cfg['slots_max']) {
            throw new InquiryRuleException('invalid_slots', "Proposez entre 1 et {$cfg['slots_max']} créneaux.");
        }
        $parsed = [];
        foreach ($slots as $raw) {
            try {
                $parsed[] = Carbon::parse($raw)->utc();
            } catch (\Throwable) {
                throw new InquiryRuleException('invalid_slots', 'Créneau invalide (format date et heure attendu).');
            }
        }
        usort($parsed, fn ($a, $b) => $a <=> $b);
        foreach ($parsed as $i => $slot) {
            if ($slot->lt(now()->addMinutes($cfg['min_lead_minutes'])) || $slot->gt(now()->addDays($cfg['max_days_ahead']))) {
                throw new InquiryRuleException('invalid_slots', "Les créneaux doivent être dans plus de {$cfg['min_lead_minutes']} minutes et dans les {$cfg['max_days_ahead']} prochains jours.");
            }
            if ($i > 0 && $slot->diffInMinutes($parsed[$i - 1], true) < $cfg['duration_minutes']) {
                throw new InquiryRuleException('invalid_slots', 'Les créneaux proposés doivent être distincts.');
            }
        }

        return array_map(fn (Carbon $c) => $c->toIso8601String(), $parsed);
    }

    /** @return array{0: Visit, 1: Inquiry} visite et demande verrouillées (sérialise les confirmations concurrentes). */
    private function lock(Visit $visit): array
    {
        Listing::whereKey($visit->listing_id)->lockForUpdate()->first();
        $fresh = Visit::whereKey($visit->id)->lockForUpdate()->firstOrFail();
        $inquiry = Inquiry::whereKey($fresh->inquiry_id)->lockForUpdate()->firstOrFail();

        return [$fresh, $inquiry];
    }

    /** @param list<V> $allowed */
    private function assertStatus(Visit $visit, array $allowed): void
    {
        if (! in_array($visit->status, $allowed, true)) {
            throw new InquiryRuleException('invalid_visit_state', "Action impossible : la visite est « {$visit->status->value} ».");
        }
    }

    private function assertParty(Inquiry $inquiry, User $actor): void
    {
        if (! $this->access->isRequester($actor, $inquiry) && $this->access->ownerSide($actor, $inquiry) !== InquiryAccess::RESPOND) {
            throw new InquiryRuleException('forbidden', 'Action non autorisée sur cette visite.', 403);
        }
    }

    private function sideOf(Inquiry $inquiry, int $userId): string
    {
        return $userId === $inquiry->requester_id ? 'requester' : 'owner';
    }

    /** L'acteur doit être de l'autre côté que l'auteur de la dernière proposition. */
    private function assertOtherSide(Visit $visit, Inquiry $inquiry, User $actor): void
    {
        $this->assertParty($inquiry, $actor);
        $actorSide = $this->access->isRequester($actor, $inquiry) ? 'requester' : 'owner';
        if ($actorSide === $this->sideOf($inquiry, $visit->proposed_by)) {
            throw new InquiryRuleException('wait_other_party', 'C\'est à l\'autre partie de répondre à votre proposition.', 403);
        }
    }

    private function matchSlot(Visit $visit, string $slot): Carbon
    {
        try {
            $wanted = Carbon::parse($slot)->utc();
        } catch (\Throwable) {
            throw new InquiryRuleException('invalid_slot', 'Créneau invalide.');
        }
        foreach ($visit->proposed_slots as $proposed) {
            if (Carbon::parse($proposed)->equalTo($wanted)) {
                return $wanted;
            }
        }
        throw new InquiryRuleException('invalid_slot', 'Ce créneau ne fait pas partie des créneaux proposés.');
    }

    /** Pas deux visites confirmées proches sur la même annonce, ni dans l'agenda de la même personne ou du même demandeur. */
    private function assertNoConflict(Visit $visit, Inquiry $inquiry, Carbon $at): void
    {
        $window = (int) config('chezvous.inquiries.visit.duration_minutes');
        $from = $at->copy()->subMinutes($window - 1);
        $to = $at->copy()->addMinutes($window - 1);
        $base = fn () => Visit::query()->where('visits.status', V::Confirmed->value)->where('visits.id', '!=', $visit->id)->whereBetween('visits.scheduled_at', [$from, $to]);

        if ($base()->where('visits.listing_id', $visit->listing_id)->exists()) {
            throw new InquiryRuleException('slot_taken', 'Ce créneau est déjà réservé pour ce bien.');
        }
        $responsible = $this->access->responsibleUserId($inquiry);
        $busy = $base()->join('inquiries', 'inquiries.id', '=', 'visits.inquiry_id')->where(function ($q) use ($responsible, $inquiry) {
            $q->whereRaw('COALESCE(inquiries.assigned_to, inquiries.owner_user_id) = ?', [$responsible])->orWhere('inquiries.requester_id', $inquiry->requester_id);
        })->exists();
        if ($busy) {
            throw new InquiryRuleException('slot_conflict', 'L\'un des participants a déjà une visite confirmée à cette heure.');
        }
    }

    private function notifyOther(Inquiry $inquiry, User $actor, string $type, Visit $visit): void
    {
        $data = ['inquiry' => $inquiry->ulid, 'visit' => $visit->ulid, 'listing' => $inquiry->listing->reference];
        if ($this->access->isRequester($actor, $inquiry)) {
            $this->notifications->notifyMany($this->access->ownerSideRecipients($inquiry), $type, $data);
        } else {
            $this->notifications->notify($inquiry->requester_id, $type, $data);
        }
    }
}
