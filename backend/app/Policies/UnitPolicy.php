<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Droits sur une unité : son propriétaire (compte personnel) ou, pour une unité d'agence, le gérant ;
 * un agent seulement si l'unité lui est attribuée. Tout le reste est présenté comme un 404.
 */
class UnitPolicy
{
    public function view(User $user, Unit $unit): Response
    {
        return $this->allowed($user, $unit) ? Response::allow() : Response::denyAsNotFound();
    }

    public function manage(User $user, Unit $unit): Response
    {
        return $this->allowed($user, $unit) ? Response::allow() : Response::denyAsNotFound();
    }

    private function allowed(User $user, Unit $unit): bool
    {
        if (! $user->isActive()) {
            return false;
        }
        if ($unit->agency_id === null) {
            return $user->id === $unit->owner_user_id;
        }
        $member = $user->activeMembership();

        return $member !== null && $member->agency_id === $unit->agency_id
            && ($member->isManager() || $unit->assigned_to === $user->id);
    }
}
