<?php

namespace App\Services\Commercial;

use App\Enums\ListingStatus;
use App\Enums\PremiumKind;
use App\Enums\PremiumSource;
use App\Enums\PremiumStatus as P;
use App\Exceptions\CommercialRuleException;
use App\Models\Agency;
use App\Models\Listing;
use App\Models\PremiumListing;
use App\Models\PremiumProduct;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Engagement\NotificationService;
use App\Services\Search\ListingSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Mises en avant : « Remontée » (haut des résultats) et « À la une » (page d'accueil), pour une annonce publiée,
 * pour une durée fixe. Une mise en avant n'achète que de la VISIBILITÉ : aucun contrôle de confiance n'en dépend.
 * Si l'annonce quitte l'état publié, la mise en avant est suspendue et le temps restant n'est pas rendu.
 */
class PremiumService
{
    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly PaymentService $payments,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
        private readonly ListingSearch $search,
    ) {}

    /**
     * Demande d'une option. Si la formule inclut encore des crédits, l'option est active tout de suite ;
     * sinon elle reste en attente d'un encaissement confirmé.
     */
    public function request(User $actor, Listing $listing, PremiumProduct $product): PremiumListing
    {
        if ($listing->status !== ListingStatus::Published) {
            throw new CommercialRuleException('listing_not_published', 'Seule une annonce publiée peut être mise en avant.');
        }
        if (! $product->is_active) {
            throw new CommercialRuleException('product_unavailable', 'Cette option n\'est pas proposée pour le moment.');
        }

        try {
            return DB::transaction(function () use ($actor, $listing, $product) {
                $viaCredit = $this->creditsLeft($listing) > 0;
                $premium = new PremiumListing([
                    'listing_id' => $listing->id, 'premium_product_id' => $product->id, 'kind' => $product->kind, 'requested_by' => $actor->id,
                    'source' => $viaCredit ? PremiumSource::Credit : PremiumSource::Payment,
                    'product_snapshot' => ['code' => $product->code, 'kind' => $product->kind->value, 'duration_days' => $product->duration_days, 'price_xof' => $viaCredit ? 0 : $product->price_xof],
                ]);
                if (! $viaCredit) {
                    $next = DB::selectOne("select nextval('payment_reference_seq') as n")->n;
                    $premium->payment_reference = sprintf('PAY-%d-%06d', now()->year, $next);
                }
                $premium->forceFill(['status' => P::Pending])->save();
                $this->audit->record('premium.requested', $premium, null, ['product' => $product->code, 'via' => $premium->source->value], $actor->id);

                if ($viaCredit) {
                    $this->start($premium, null);
                } else {
                    $this->notifications->notifyMany(User::role(['admin', 'super_admin'])->get(), 'premium.requested', ['premium' => $premium->ulid, 'listing' => $listing->reference]);
                }

                return $premium;
            });
        } catch (UniqueConstraintViolationException) {
            throw new CommercialRuleException('premium_open', 'Une option de ce type est déjà active ou en attente sur cette annonce.');
        }
    }

    /** Activation par un administrateur, après encaissement confirmé. */
    public function activate(PremiumListing $premium, User $admin): PremiumListing
    {
        return DB::transaction(function () use ($premium, $admin) {
            $locked = PremiumListing::whereKey($premium->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, [P::Pending]);
            if ($locked->listing->status !== ListingStatus::Published) {
                throw new CommercialRuleException('listing_not_published', 'L\'annonce n\'est plus publiée.');
            }
            $price = (int) $locked->product_snapshot['price_xof'];
            if ($this->payments->settlementStatus($locked) !== 'confirmed' || $this->payments->confirmedAmount($locked) < $price) {
                throw new CommercialRuleException('payment_required', 'Un encaissement confirmé couvrant le prix de l\'option est requis avant activation.');
            }
            $this->start($locked, $admin);

            return $locked;
        });
    }

    public function suspend(PremiumListing $premium, User $admin, string $reason): PremiumListing
    {
        $this->assertStatus($premium, [P::Active]);
        $premium->forceFill(['status' => P::Suspended, 'reason' => $reason])->save();
        $this->syncBoost($premium->listing);
        $this->audit->record('premium.suspended', $premium, ['status' => 'active'], ['status' => 'suspended', 'reason' => $reason], $admin->id);

        return $premium;
    }

    /** Annulation d'une option en attente (par son demandeur ou un administrateur). */
    public function cancelPending(PremiumListing $premium, User $actor, string $reason): PremiumListing
    {
        $this->assertStatus($premium, [P::Pending]);
        $premium->forceFill(['status' => P::Cancelled, 'reason' => $reason])->save();
        $this->audit->record('premium.cancelled', $premium, ['status' => 'pending'], ['status' => 'cancelled'], $actor->id);

        return $premium;
    }

    /** Quand une annonce quitte l'état publié, ses options actives sont suspendues (temps restant non rendu). */
    public function onListingStatusChanged(Listing $listing, ListingStatus $from, ListingStatus $to): void
    {
        if ($from === ListingStatus::Published && $to !== ListingStatus::Published) {
            $affected = PremiumListing::where('listing_id', $listing->id)->where('status', P::Active->value)
                ->update(['status' => P::Suspended->value, 'reason' => 'listing_not_published']);
            if ($affected > 0) {
                $this->audit->record('premium.suspended', $listing, null, ['reason' => 'listing_not_published', 'count' => $affected], null);
            }
            $this->syncBoost($listing);
        }
    }

    /** @return int nombre d'options expirées */
    public function expireDue(): int
    {
        $count = 0;
        PremiumListing::where('status', P::Active->value)->where('ends_at', '<=', now())->each(function (PremiumListing $p) use (&$count) {
            $p->forceFill(['status' => P::Expired])->save();
            $this->syncBoost($p->listing);
            $this->audit->record('premium.expired', $p, ['status' => 'active'], ['status' => 'expired'], null);
            $this->notifications->notify($p->requested_by, 'premium.expired', ['premium' => $p->ulid, 'listing' => $p->listing->reference]);
            $count++;
        });
        // Filet de sécurité : aucun drapeau « remontée » ne survit à la fin de l'option.
        DB::update("UPDATE listings SET is_boosted = false WHERE is_boosted AND NOT EXISTS (SELECT 1 FROM premium_listings p WHERE p.listing_id = listings.id AND p.kind = 'boost' AND p.status = 'active' AND p.ends_at > ?)", [now()]);

        return $count;
    }

    /** Crédits de mise en avant restants dans la période de la souscription en cours. */
    public function creditsLeft(Listing $listing): int
    {
        $subscriber = $this->entitlements->subscriberOf($listing);
        $rights = $this->entitlements->for($subscriber);
        $total = (int) ($rights['limits']['featured_credits'] ?? 0);
        if ($total <= 0 || $rights['subscription'] === null) {
            return 0;
        }
        $since = Subscription::where('ulid', $rights['subscription'])->value('starts_at');
        $ids = $subscriber instanceof Agency ? Listing::where('agency_id', $subscriber->id)->select('id') : Listing::where('advertiser_user_id', $subscriber->id)->whereNull('agency_id')->select('id');
        $used = PremiumListing::whereIn('listing_id', $ids)->where('source', PremiumSource::Credit->value)->where('created_at', '>=', $since)->count();

        return max(0, $total - $used);
    }

    /** Recalcule le drapeau de tri « remontée » d'une annonce. */
    public function syncBoost(Listing $listing): void
    {
        $boosted = $listing->fresh()->status === ListingStatus::Published && PremiumListing::where('listing_id', $listing->id)
            ->where('kind', PremiumKind::Boost->value)->where('status', P::Active->value)->where('ends_at', '>', now())->exists();
        Listing::whereKey($listing->id)->update(['is_boosted' => $boosted]);
    }

    /**
     * Annonces « À la une » : plafonnées et en rotation équitable (l'ordre change par tranche de quelques heures,
     * de façon déterministe, pour que chacune soit montrée).
     *
     * @return Collection<int, Listing>
     */
    public function featured(): Collection
    {
        $slot = intdiv(now()->timestamp, max(1, config('chezvous.commercial.featured_rotation_hours')) * 3600);

        return $this->search->visible()
            ->whereIn('id', PremiumListing::where('kind', PremiumKind::Featured->value)->where('status', P::Active->value)->where('ends_at', '>', now())->select('listing_id'))
            ->orderByRaw('md5(listings.id::text || ?)', [(string) $slot])
            ->limit(config('chezvous.commercial.featured_display_cap'))->get();
    }

    private function start(PremiumListing $premium, ?User $admin): void
    {
        $days = (int) $premium->product_snapshot['duration_days'];
        $premium->forceFill(['status' => P::Active, 'activated_by' => $admin?->id, 'starts_at' => now(), 'ends_at' => now()->addDays($days)])->save();
        $this->syncBoost($premium->listing);
        $this->audit->record('premium.activated', $premium, ['status' => 'pending'], ['status' => 'active', 'ends_at' => $premium->ends_at?->toIso8601String(), 'via' => $premium->source->value], $admin?->id);
        $this->notifications->notify($premium->requested_by, 'premium.activated', ['premium' => $premium->ulid, 'listing' => $premium->listing->reference, 'ends_at' => $premium->ends_at?->toIso8601String()]);
    }

    /** @param list<P> $allowed */
    private function assertStatus(PremiumListing $premium, array $allowed): void
    {
        if (! in_array($premium->status, $allowed, true)) {
            throw new CommercialRuleException('invalid_transition', "Action impossible : l'option est « {$premium->status->value} ».");
        }
    }
}
