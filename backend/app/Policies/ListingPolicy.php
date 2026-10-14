<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Droits sur une annonce. Les refus qui révéleraient l'existence de l'annonce d'autrui sont présentés comme un 404.
 *
 * - Annonce personnelle : seul son annonceur.
 * - Annonce d'agence : le gérant (toutes) ; un agent peut la modifier si elle lui est attribuée,
 *   et la voir si elle lui est attribuée ou s'il a le droit étendu. Un membre suspendu n'a plus aucun accès.
 */
class ListingPolicy
{
    public function view(User $user, Listing $listing): Response
    {
        return $this->canView($user, $listing) ? Response::allow() : Response::denyAsNotFound();
    }

    public function manage(User $user, Listing $listing): Response
    {
        if ($listing->agency_id === null) {
            return $user->id === $listing->advertiser_user_id ? Response::allow() : Response::denyAsNotFound();
        }

        $member = $user->activeMembership();
        if ($member === null || $member->agency_id !== $listing->agency_id) {
            return Response::denyAsNotFound();
        }
        if ($member->isManager() || $listing->assigned_to === $user->id) {
            return Response::allow();
        }

        // Il peut voir l'annonce (droit étendu) mais pas la modifier : refus explicite plutôt que 404.
        return $member->can_see_all ? Response::deny('Cette annonce est attribuée à un autre membre de l\'agence.') : Response::denyAsNotFound();
    }

    /** Attribuer ou réattribuer : gérant de l'agence propriétaire de l'annonce. */
    public function assign(User $user, Listing $listing): Response
    {
        $member = $user->activeMembership();
        if ($listing->agency_id === null || $member === null || $member->agency_id !== $listing->agency_id) {
            return Response::denyAsNotFound();
        }

        if ($member->isManager()) {
            return Response::allow();
        }

        // Un agent qui ne peut même pas voir l'annonce ne doit pas en deviner l'existence.
        return $this->canView($user, $listing) ? Response::deny('Réservé au gérant de l\'agence.') : Response::denyAsNotFound();
    }

    private function canView(User $user, Listing $listing): bool
    {
        if ($listing->agency_id === null) {
            return $user->id === $listing->advertiser_user_id;
        }
        $member = $user->activeMembership();

        return $member !== null && $member->agency_id === $listing->agency_id
            && ($member->isManager() || $member->can_see_all || $listing->assigned_to === $user->id);
    }
}
