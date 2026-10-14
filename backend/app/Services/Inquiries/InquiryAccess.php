<?php

namespace App\Services\Inquiries;

use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Models\AgencyMember;
use App\Models\Inquiry;
use App\Models\User;

/**
 * Qui peut quoi sur une demande. Les coordonnées du demandeur ne sont visibles que du demandeur lui-même
 * et du côté annonceur autorisé (DEM-06).
 *
 * Côté annonceur : annonce personnelle = l'annonceur ; annonce d'agence = le gérant (tout), l'agent attribué (répond),
 * un agent à droit étendu (lit seulement). Un membre suspendu n'a plus aucun accès.
 */
class InquiryAccess
{
    public const NONE = 'none';

    public const VIEW = 'view';

    public const RESPOND = 'respond';

    public function isRequester(User $user, Inquiry $inquiry): bool
    {
        return $user->id === $inquiry->requester_id;
    }

    /** Niveau d'accès côté annonceur : none | view | respond. */
    public function ownerSide(User $user, Inquiry $inquiry): string
    {
        if ($inquiry->agency_id === null) {
            return $user->id === $inquiry->owner_user_id ? self::RESPOND : self::NONE;
        }

        $member = $user->activeMembership();
        if ($member === null || $member->agency_id !== $inquiry->agency_id) {
            return self::NONE;
        }
        if ($member->isManager() || $inquiry->assigned_to === $user->id) {
            return self::RESPOND;
        }

        return $member->can_see_all ? self::VIEW : self::NONE;
    }

    /** @return list<User> personnes à prévenir côté annonceur (l'attribué, sinon l'annonceur ou le gérant). */
    public function ownerSideRecipients(Inquiry $inquiry): array
    {
        if ($inquiry->assigned_to !== null) {
            return array_filter([User::find($inquiry->assigned_to)]);
        }
        if ($inquiry->agency_id === null) {
            return array_filter([User::find($inquiry->owner_user_id)]);
        }

        return AgencyMember::where('agency_id', $inquiry->agency_id)->where('role', MemberRole::Manager->value)
            ->where('status', MemberStatus::Active->value)->with('user')->get()->map(fn ($m) => $m->user)->all();
    }

    /** Personne dont l'agenda porte la visite (pour détecter les conflits). */
    public function responsibleUserId(Inquiry $inquiry): int
    {
        return $inquiry->assigned_to ?? $inquiry->owner_user_id;
    }
}
