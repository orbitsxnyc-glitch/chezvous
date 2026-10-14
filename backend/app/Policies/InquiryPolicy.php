<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\Inquiries\InquiryAccess;
use Illuminate\Auth\Access\Response;

/** Les refus qui révéleraient l'existence d'une demande sont présentés comme un 404. */
class InquiryPolicy
{
    public function __construct(private readonly InquiryAccess $access) {}

    public function view(User $user, Inquiry $inquiry): Response
    {
        return $this->access->isRequester($user, $inquiry) || $this->access->ownerSide($user, $inquiry) !== InquiryAccess::NONE
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** Répondre, changer le statut, ajouter une note : côté annonceur avec droit de réponse. */
    public function respond(User $user, Inquiry $inquiry): Response
    {
        return match ($this->access->ownerSide($user, $inquiry)) {
            InquiryAccess::RESPOND => Response::allow(),
            InquiryAccess::VIEW => Response::deny('Cette demande est attribuée à un autre membre de l\'agence.'),
            default => Response::denyAsNotFound(),
        };
    }

    /** Attribution : gérant de l'agence concernée. */
    public function assign(User $user, Inquiry $inquiry): Response
    {
        $member = $user->activeMembership();
        if ($inquiry->agency_id === null || $member === null || $member->agency_id !== $inquiry->agency_id) {
            return Response::denyAsNotFound();
        }

        return $member->isManager() ? Response::allow() : (
            $this->access->ownerSide($user, $inquiry) !== InquiryAccess::NONE ? Response::deny('Réservé au gérant de l\'agence.') : Response::denyAsNotFound()
        );
    }
}
