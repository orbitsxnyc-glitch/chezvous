<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Droits sur une réservation. Le voyageur agit sur la sienne ; l'hôte (propriétaire, gérant d'agence, agent attribué)
 * agit via les droits de l'unité. Toute autre personne reçoit un 404.
 */
class BookingPolicy
{
    public function asGuest(User $user, Booking $booking): Response
    {
        return $user->isActive() && $booking->guest_user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function asHost(User $user, Booking $booking): Response
    {
        return Gate::forUser($user)->allows('manage', $booking->unit) ? Response::allow() : Response::denyAsNotFound();
    }

    public function view(User $user, Booking $booking): Response
    {
        return $this->asGuest($user, $booking)->allowed() ? Response::allow() : $this->asHost($user, $booking);
    }
}
