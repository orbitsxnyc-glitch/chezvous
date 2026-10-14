<?php

namespace App\Services\Listings;

use App\Enums\ImageStatus;
use App\Enums\ListingStatus as S;
use App\Events\ListingStatusChanged;
use App\Exceptions\ListingRuleException;
use App\Models\Listing;
use App\Models\ModerationReason;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Seul point d'entrée pour changer l'état d'une annonce. Vérifie la légalité de la transition,
 * les règles métier (quota, complétude, photo de couverture), écrit dans audit_logs et émet un événement.
 * L'autorisation de l'acteur (qui a le droit) relève des Policies, pas de ce service.
 */
class ListingStateService
{
    /** @var array<string, list<S>> */
    private const TRANSITIONS = [
        'draft' => [S::PendingModeration, S::Published, S::Archived],
        'pending_moderation' => [S::Published, S::Rejected, S::Archived],
        'rejected' => [S::PendingModeration, S::Archived],
        'published' => [S::Paused, S::Closed, S::Expired, S::Hidden, S::Archived],
        'paused' => [S::Published, S::Archived],
        'closed' => [S::Archived],
        'expired' => [S::PendingModeration, S::Published, S::Archived],
        'hidden' => [S::Archived],
        'archived' => [],
    ];

    public function __construct(private readonly QuotaService $quota, private readonly AuditLogger $audit) {}

    public function canTransition(S $from, S $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    /** Soumission par l'annonceur (brouillon, refusée ou expirée) : modération ou publication directe. */
    public function submit(Listing $listing, User $actor): Listing
    {
        return $this->run($listing, $actor, function (Listing $l) use ($actor) {
            $this->assertComplete($l);
            $this->assertQuota($l);
            $target = $this->isTrusted($l->advertiser) ? S::Published : S::PendingModeration;
            if ($l->status === S::Rejected) {
                $target = S::PendingModeration; // une annonce refusée repasse toujours par la modération
            }

            return $this->apply($l, $target, $actor);
        });
    }

    public function approve(Listing $listing, User $moderator): Listing
    {
        return $this->run($listing, $moderator, fn (Listing $l) => $this->apply($l, S::Published, $moderator, approvedByModerator: true));
    }

    public function reject(Listing $listing, User $moderator, string $reasonCode, ?string $comment = null): Listing
    {
        return $this->run($listing, $moderator, function (Listing $l) use ($moderator, $reasonCode, $comment) {
            if (! ModerationReason::where('code', $reasonCode)->where('kind', 'rejection')->where('is_active', true)->exists()) {
                throw new ListingRuleException('invalid_reason', 'Motif de refus obligatoire et valide.');
            }

            return $this->apply($l, S::Rejected, $moderator, $reasonCode, $comment);
        });
    }

    public function pause(Listing $listing, User $actor): Listing
    {
        return $this->run($listing, $actor, fn (Listing $l) => $this->apply($l, S::Paused, $actor));
    }

    public function resume(Listing $listing, User $actor): Listing
    {
        return $this->run($listing, $actor, function (Listing $l) use ($actor) {
            $this->assertQuota($l);

            return $this->apply($l, S::Published, $actor);
        });
    }

    /** Vendue ou louée. */
    public function close(Listing $listing, User $actor): Listing
    {
        return $this->run($listing, $actor, fn (Listing $l) => $this->apply($l, S::Closed, $actor));
    }

    public function expire(Listing $listing): Listing
    {
        return $this->run($listing, null, fn (Listing $l) => $this->apply($l, S::Expired, null, 'expired'));
    }

    public function hide(Listing $listing, User $moderator, ?string $reasonCode = null): Listing
    {
        return $this->run($listing, $moderator, fn (Listing $l) => $this->apply($l, S::Hidden, $moderator, $reasonCode));
    }

    public function archive(Listing $listing, ?User $actor): Listing
    {
        return $this->run($listing, $actor, fn (Listing $l) => $this->apply($l, S::Archived, $actor));
    }

    /**
     * Un compte est « de confiance » (H7) s'il a un téléphone vérifié et au moins N annonces
     * déjà approuvées par un modérateur. Les annonces suivantes sont publiées directement (mais restent signalables).
     */
    public function isTrusted(User $user): bool
    {
        if (! $user->hasVerifiedPhone()) {
            return false;
        }

        $approvals = Listing::withTrashed()->where('advertiser_user_id', $user->id)->whereNotNull('approved_at')->count();

        return $approvals >= config('chezvous.listings.trusted_after_approvals');
    }

    /**
     * @param  callable(Listing): Listing  $callback
     */
    private function run(Listing $listing, ?User $actor, callable $callback): Listing
    {
        return DB::transaction(function () use ($listing, $callback) {
            // Verrou sur l'annonce et sur l'annonceur : évite deux soumissions simultanées qui dépasseraient le quota.
            User::whereKey($listing->advertiser_user_id)->lockForUpdate()->first();
            $fresh = Listing::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $result = $callback($fresh);
            $listing->setRawAttributes($result->getAttributes(), true);

            return $listing;
        });
    }

    private function apply(Listing $l, S $to, ?User $actor, ?string $reasonCode = null, ?string $comment = null, bool $approvedByModerator = false): Listing
    {
        $from = $l->status;
        if (! $this->canTransition($from, $to)) {
            throw new ListingRuleException('invalid_transition', "Transition interdite : {$from->value} vers {$to->value}.", ['from' => $from->value, 'to' => $to->value]);
        }

        $l->status = $to;
        match ($to) {
            S::PendingModeration => $l->forceFill(['submitted_at' => now(), 'rejection_reason_code' => null, 'rejection_comment' => null]),
            S::Published => $this->markPublished($l, $from, $approvedByModerator),
            S::Rejected => $l->forceFill(['rejection_reason_code' => $reasonCode, 'rejection_comment' => $comment]),
            S::Closed => $l->forceFill(['closed_at' => now()]),
            default => null,
        };
        $l->save();

        $this->audit->record($approvedByModerator ? 'listing.approved' : 'listing.status_changed', $l,
            ['status' => $from->value], array_filter(['status' => $to->value, 'reason' => $reasonCode]), $actor?->id);
        ListingStatusChanged::dispatch($l, $from, $to, $reasonCode);

        return $l;
    }

    private function markPublished(Listing $l, S $from, bool $approvedByModerator): void
    {
        if ($approvedByModerator) {
            $l->forceFill(['approved_at' => now()]);
        }

        // La date de publication et l'expiration ne sont posées qu'à la première publication ou à une republication.
        if ($from !== S::Paused || $l->published_at === null) {
            $l->forceFill([
                'published_at' => now(),
                'expires_at' => now()->addDays(config('chezvous.listings.expiry_days')),
                'expiry_reminded_at' => null,
                'closed_at' => null,
            ]);
        }
    }

    private function assertQuota(Listing $l): void
    {
        $user = $l->advertiser;
        if (! $this->quota->hasRoom($user, $l)) {
            throw new ListingRuleException('quota_exceeded',
                'Vous avez atteint le nombre maximal d\'annonces actives. Mettez une annonce en pause ou archivez-en une.',
                ['limit' => $this->quota->limitFor($user)]);
        }
    }

    /** Champs obligatoires pour soumettre (ANN-01, ANN-04, ANN-07). */
    private function assertComplete(Listing $l): void
    {
        $p = $l->property;
        $missing = array_keys(array_filter([
            'title' => blank($l->title),
            'description' => blank($l->description),
            'price_xof' => ($l->price_xof ?? 0) <= 0,
            'locality_id' => $p->locality_id === null,
            'surface_m2' => ($p->surface_m2 ?? 0) <= 0,
            'cover_image' => ! $p->images()->where('is_cover', true)->where('status', ImageStatus::Ready->value)->exists(),
        ]));

        if ($missing !== []) {
            throw new ListingRuleException('incomplete_listing', 'Annonce incomplète : renseignez les champs obligatoires et une photo de couverture.', ['missing' => $missing]);
        }
    }
}
