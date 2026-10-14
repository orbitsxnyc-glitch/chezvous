<?php

namespace App\Services\Inquiries;

use App\Enums\InquiryStatus;
use App\Enums\MemberStatus;
use App\Enums\PreferredChannel;
use App\Exceptions\InquiryRuleException;
use App\Models\AgencyMember;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\InquiryNote;
use App\Models\Listing;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Engagement\NotificationService;
use App\Services\Search\ListingCounters;
use App\Services\Search\ListingSearch;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InquiryService
{
    public function __construct(
        private readonly ListingSearch $search,
        private readonly ListingCounters $counters,
        private readonly InquiryAccess $access,
        private readonly InquiryStateService $states,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Ouvre une demande de contact (DEM-01). Si une demande est déjà ouverte pour cette annonce, le message
     * est ajouté au fil existant (une seule demande ouverte par demandeur et par annonce).
     *
     * @return array{0: Inquiry, 1: bool} la demande et un indicateur « créée »
     */
    public function open(string $listingUlid, User $requester, string $body, ?string $phone, PreferredChannel $channel): array
    {
        // Annonce publique uniquement : le reste n'existe pas pour le public.
        $listing = $this->search->visible()->where('ulid', $listingUlid)->firstOrFail();

        if ($listing->advertiser_user_id === $requester->id || $this->isTeamMember($requester, $listing)) {
            throw new InquiryRuleException('own_listing', 'Vous ne pouvez pas contacter votre propre annonce.');
        }
        $normalized = $phone !== null && $phone !== '' ? PhoneNumber::normalize($phone) : $requester->phone;
        if ($normalized === null) {
            throw new InquiryRuleException('invalid_phone', 'Numéro de téléphone sénégalais invalide.');
        }

        try {
            return DB::transaction(function () use ($listing, $requester, $body, $normalized, $channel) {
                $existing = Inquiry::where('listing_id', $listing->id)->where('requester_id', $requester->id)
                    ->whereNotIn('status', ['concluded', 'lost', 'closed'])->lockForUpdate()->first();
                if ($existing !== null) {
                    $this->addMessage($existing, $requester, $body);

                    return [$existing, false];
                }

                $inquiry = new Inquiry([
                    'listing_id' => $listing->id, 'requester_id' => $requester->id, 'owner_user_id' => $listing->advertiser_user_id,
                    'agency_id' => $listing->agency_id, 'assigned_to' => $listing->assigned_to, 'preferred_channel' => $channel,
                    'requester_phone' => $normalized, 'last_activity_at' => now(),
                ]);
                $inquiry->forceFill(['status' => InquiryStatus::New])->save();
                InquiryMessage::create(['inquiry_id' => $inquiry->id, 'sender_id' => $requester->id, 'body' => $body]);

                // Contact compté une fois par visiteur et par jour (RCH-07) ; la clé est un condensé, jamais l'identité.
                $this->counters->contact($listing, ListingCounters::visitorKey($requester->id, null, null));
                $this->audit->record('inquiry.created', $inquiry, null, ['listing' => $listing->reference], $requester->id);
                $this->notifications->notifyMany($this->access->ownerSideRecipients($inquiry), 'inquiry.new', ['inquiry' => $inquiry->ulid, 'listing' => $listing->reference]);

                return [$inquiry, true];
            });
        } catch (UniqueConstraintViolationException) {
            // Deux demandes simultanées : la seconde reprend le fil créé par la première.
            $existing = Inquiry::where('listing_id', $listing->id)->where('requester_id', $requester->id)->whereNotIn('status', ['concluded', 'lost', 'closed'])->firstOrFail();
            $this->addMessage($existing, $requester, $body);

            return [$existing, false];
        }
    }

    /** Envoi d'un message par l'une des deux parties. */
    public function addMessage(Inquiry $inquiry, User $sender, string $body): InquiryMessage
    {
        if (! $inquiry->isOpen()) {
            throw new InquiryRuleException('inquiry_closed', 'Cette demande est terminée : ouvrez une nouvelle demande.');
        }
        $fromRequester = $this->access->isRequester($sender, $inquiry);

        $message = InquiryMessage::create(['inquiry_id' => $inquiry->id, 'sender_id' => $sender->id, 'body' => $body]);
        $inquiry->forceFill(['last_activity_at' => now()]);

        if (! $fromRequester && $inquiry->first_response_at === null) {
            $inquiry->first_response_at = now();
            if ($inquiry->status === InquiryStatus::New) {
                $inquiry->save();
                $this->states->apply($inquiry, InquiryStatus::InProgress, $sender); // « en cours » dès la première réponse
            }
        }
        $inquiry->save();

        $data = ['inquiry' => $inquiry->ulid, 'listing' => $inquiry->listing->reference];
        if ($fromRequester) {
            $this->notifications->notifyMany($this->access->ownerSideRecipients($inquiry), 'inquiry.message', $data);
        } else {
            $this->notifications->notify($inquiry->requester_id, 'inquiry.message', $data);
        }

        return $message;
    }

    /** Marque comme lus les messages de l'autre partie. */
    public function markRead(Inquiry $inquiry, User $reader): void
    {
        $fromRequester = $this->access->isRequester($reader, $inquiry);
        $inquiry->messages()->whereNull('read_at')
            ->when($fromRequester, fn ($q) => $q->where('sender_id', '!=', $inquiry->requester_id), fn ($q) => $q->where('sender_id', $inquiry->requester_id))
            ->update(['read_at' => now()]);
    }

    /** Attribution par le gérant à un membre actif de l'agence. */
    public function assign(Inquiry $inquiry, AgencyMember $target, User $manager): Inquiry
    {
        if ($inquiry->agency_id === null || $target->agency_id !== $inquiry->agency_id || $target->status !== MemberStatus::Active) {
            throw new InquiryRuleException('invalid_assignee', 'Le destinataire doit être un membre actif de l\'agence.');
        }
        $before = $inquiry->assigned_to;
        $inquiry->forceFill(['assigned_to' => $target->user_id])->save();
        $this->audit->record('inquiry.assigned', $inquiry, ['assigned_to' => $before], ['assigned_to' => $target->user_id], $manager->id);
        $this->notifications->notify($target->user_id, 'inquiry.assigned', ['inquiry' => $inquiry->ulid, 'listing' => $inquiry->listing->reference]);

        return $inquiry;
    }

    /** Note interne : jamais visible du demandeur (DEM-05). */
    public function addNote(Inquiry $inquiry, User $author, string $body): InquiryNote
    {
        return InquiryNote::create(['inquiry_id' => $inquiry->id, 'author_id' => $author->id, 'body' => $body]);
    }

    private function isTeamMember(User $user, Listing $listing): bool
    {
        return $listing->agency_id !== null && AgencyMember::where('agency_id', $listing->agency_id)->where('user_id', $user->id)->exists();
    }
}
