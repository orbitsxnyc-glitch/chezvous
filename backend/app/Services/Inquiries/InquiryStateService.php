<?php

namespace App\Services\Inquiries;

use App\Enums\InquiryStatus as S;
use App\Exceptions\InquiryRuleException;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Engagement\NotificationService;

/** Seul point d'entrée pour changer le statut d'une demande (audit à chaque transition). */
class InquiryStateService
{
    /** @var array<string, list<S>> */
    private const TRANSITIONS = [
        'new' => [S::InProgress, S::Lost, S::Closed],
        'in_progress' => [S::VisitScheduled, S::Concluded, S::Lost, S::Closed],
        'visit_scheduled' => [S::InProgress, S::Concluded, S::Lost, S::Closed],
        'concluded' => [],
        'lost' => [],
        'closed' => [],
    ];

    public function __construct(private readonly AuditLogger $audit, private readonly NotificationService $notifications, private readonly InquiryAccess $access) {}

    public function canTransition(S $from, S $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    /**
     * Changement demandé par un utilisateur. Le demandeur ne peut que fermer sa propre demande ;
     * le côté annonceur (avec droit de réponse) peut tout le reste.
     */
    public function change(Inquiry $inquiry, User $actor, S $to): Inquiry
    {
        $isRequester = $this->access->isRequester($actor, $inquiry);
        if ($isRequester && $to !== S::Closed) {
            throw new InquiryRuleException('forbidden_status', 'Vous pouvez seulement fermer votre demande.', 403);
        }

        return $this->apply($inquiry, $to, $actor);
    }

    /** Transition interne (réponse, visite confirmée, etc.). */
    public function apply(Inquiry $inquiry, S $to, ?User $actor): Inquiry
    {
        $from = $inquiry->status;
        if (! $this->canTransition($from, $to)) {
            throw new InquiryRuleException('invalid_transition', "Transition interdite : {$from->value} vers {$to->value}.");
        }
        $inquiry->status = $to;
        if (! $inquiry->isOpen()) {
            $inquiry->closed_at = now();
        }
        $inquiry->last_activity_at = now();
        $inquiry->save();
        $this->audit->record('inquiry.status_changed', $inquiry, ['status' => $from->value], ['status' => $to->value], $actor?->id);

        // Prévient l'autre partie lors d'une clôture ou d'un classement.
        if ($actor !== null && in_array($to, [S::Concluded, S::Lost, S::Closed], true)) {
            $listing = $inquiry->listing;
            $data = ['inquiry' => $inquiry->ulid, 'listing' => $listing->reference, 'status' => $to->value];
            if ($this->access->isRequester($actor, $inquiry)) {
                $this->notifications->notifyMany($this->access->ownerSideRecipients($inquiry), 'inquiry.status', $data);
            } else {
                $this->notifications->notify($inquiry->requester_id, 'inquiry.status', $data);
            }
        }

        return $inquiry;
    }
}
