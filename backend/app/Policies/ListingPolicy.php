<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ListingPolicy
{
    /**
     * Gérer une annonce : seul son annonceur (les droits d'agence arrivent en Phase 4).
     * Pour ne pas révéler l'existence de l'annonce d'autrui, le refus est présenté comme un 404.
     */
    public function manage(User $user, Listing $listing): Response
    {
        return $user->id === $listing->advertiser_user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
