<?php

namespace App\Listeners;

use App\Enums\ListingStatus;
use App\Events\ListingExpiringSoon;
use App\Events\ListingStatusChanged;
use App\Models\ModerationReason;
use App\Services\Engagement\NotificationService;

/**
 * Notifie l'annonceur des décisions de la modération et de l'expiration (NOT-01). L'agent attribué est prévenu aussi.
 */
class ListingNotifier
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function statusChanged(ListingStatusChanged $event): void
    {
        $listing = $event->listing;
        $data = ['listing' => $listing->reference];

        $type = match (true) {
            $event->from === ListingStatus::PendingModeration && $event->to === ListingStatus::Published => 'listing.approved',
            $event->to === ListingStatus::Rejected => 'listing.rejected',
            $event->to === ListingStatus::Hidden => 'listing.hidden',
            default => null,
        };
        if ($type === null) {
            return;
        }
        if ($type === 'listing.rejected') {
            $data['reason'] = ModerationReason::where('code', $event->reasonCode)->value('label') ?? 'Non conforme';
        }
        $this->notifications->notifyMany(array_filter([$listing->advertiser_user_id, $listing->assigned_to]), $type, $data);
    }

    public function expiringSoon(ListingExpiringSoon $event): void
    {
        $listing = $event->listing;
        $this->notifications->notifyMany(array_filter([$listing->advertiser_user_id, $listing->assigned_to]), 'listing.expiring', ['listing' => $listing->reference]);
    }
}
