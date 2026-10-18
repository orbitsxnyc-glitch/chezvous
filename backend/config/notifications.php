<?php

use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\Channels\PushChannel;
use App\Services\Notifications\Channels\SmsChannel;
use App\Services\Notifications\Channels\WhatsAppChannel;

/**
 * Catalogue des notifications (NOT-01). Un seul endroit décrit chaque événement : catégorie, variables autorisées
 * dans les modèles, canaux actifs par défaut et caractère obligatoire. Les variables ne portent jamais de coordonnées
 * personnelles (téléphone, e-mail) : seulement des références publiques.
 *
 * Canaux : « in_app » est toujours enregistré (centre de notifications). Les autres respectent les préférences.
 * WhatsApp et SMS sont des choix explicites de l'utilisateur (consentement) : désactivés par défaut.
 */
$on = ['email' => true, 'push' => true, 'whatsapp' => false, 'sms' => false];
$push = ['email' => false, 'push' => true, 'whatsapp' => false, 'sms' => false];
$mail = ['email' => true, 'push' => false, 'whatsapp' => false, 'sms' => false];

return [
    'channels' => ['email', 'push', 'whatsapp', 'sms'],

    // Pilote de chaque canal. Remplacer le bouchon par une classe de fournisseur réel quand il sera choisi (aucun compte créé).
    'drivers' => [
        'email' => EmailChannel::class,
        'push' => PushChannel::class,
        'whatsapp' => WhatsAppChannel::class,
        'sms' => SmsChannel::class,
    ],
    'max_attempts' => 3,
    'backoff_seconds' => [60, 300],

    'types' => [
        // Sécurité : non désactivables.
        'security.otp' => ['category' => 'security', 'label' => 'Code de vérification', 'vars' => [], 'defaults' => $mail, 'mandatory' => true, 'audience' => 'all'],
        'security.password_changed' => ['category' => 'security', 'label' => 'Mot de passe modifié', 'vars' => [], 'defaults' => $mail, 'mandatory' => true, 'audience' => 'all'],
        'security.two_factor_changed' => ['category' => 'security', 'label' => 'Double authentification modifiée', 'vars' => [], 'defaults' => $mail, 'mandatory' => true, 'audience' => 'admin'],
        'security.account_suspended' => ['category' => 'security', 'label' => 'Compte suspendu', 'vars' => [], 'defaults' => $mail, 'mandatory' => true, 'audience' => 'all'],

        'moderation.warning' => ['category' => 'listing', 'label' => 'Avertissement de la modération', 'vars' => ['listing', 'reason'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'listing.approved' => ['category' => 'listing', 'label' => 'Annonce approuvée', 'vars' => ['listing'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'listing.rejected' => ['category' => 'listing', 'label' => 'Annonce refusée', 'vars' => ['listing', 'reason'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'listing.hidden' => ['category' => 'listing', 'label' => 'Annonce masquée par la modération', 'vars' => ['listing'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'listing.expiring' => ['category' => 'listing', 'label' => 'Annonce bientôt expirée', 'vars' => ['listing'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],

        'inquiry.new' => ['category' => 'inquiry', 'label' => 'Nouvelle demande de contact', 'vars' => ['listing'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'inquiry.message' => ['category' => 'inquiry', 'label' => 'Nouveau message', 'vars' => ['listing'], 'defaults' => $push, 'mandatory' => false, 'audience' => 'all'],
        'inquiry.status' => ['category' => 'inquiry', 'label' => 'Statut d\'une demande', 'vars' => ['listing'], 'defaults' => $push, 'mandatory' => false, 'audience' => 'all'],
        'inquiry.assigned' => ['category' => 'inquiry', 'label' => 'Demande attribuée', 'vars' => ['listing'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'inquiry.unanswered' => ['category' => 'inquiry', 'label' => 'Demande sans réponse', 'vars' => ['listing'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],

        'visit.proposed' => ['category' => 'visit', 'label' => 'Visite proposée', 'vars' => ['listing'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'visit.confirmed' => ['category' => 'visit', 'label' => 'Visite confirmée', 'vars' => ['listing', 'when'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'visit.rescheduled' => ['category' => 'visit', 'label' => 'Visite reportée', 'vars' => ['listing'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'visit.cancelled' => ['category' => 'visit', 'label' => 'Visite annulée', 'vars' => ['listing'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'visit.reminder' => ['category' => 'visit', 'label' => 'Rappel de visite', 'vars' => ['listing', 'hours', 'when'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'visit.done' => ['category' => 'visit', 'label' => 'Visite effectuée', 'vars' => ['listing'], 'defaults' => $push, 'mandatory' => false, 'audience' => 'all'],
        'visit.feedback' => ['category' => 'visit', 'label' => 'Retour après visite', 'vars' => ['listing'], 'defaults' => $push, 'mandatory' => false, 'audience' => 'all'],

        'favorite.price_drop' => ['category' => 'favorite', 'label' => 'Baisse de prix d\'un favori', 'vars' => ['listing', 'old_price', 'new_price'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'favorite.unavailable' => ['category' => 'favorite', 'label' => 'Favori indisponible', 'vars' => ['listing'], 'defaults' => $push, 'mandatory' => false, 'audience' => 'all'],
        'search.alert' => ['category' => 'favorite', 'label' => 'Alerte de recherche', 'vars' => ['name', 'count'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],

        'booking.requested' => ['category' => 'booking', 'label' => 'Nouvelle demande de séjour', 'vars' => ['unit'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'booking.accepted' => ['category' => 'booking', 'label' => 'Demande de séjour acceptée', 'vars' => ['unit'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'booking.declined' => ['category' => 'booking', 'label' => 'Demande de séjour refusée', 'vars' => ['unit'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'booking.expired' => ['category' => 'booking', 'label' => 'Demande de séjour expirée', 'vars' => ['unit'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'booking.cancelled' => ['category' => 'booking', 'label' => 'Séjour annulé', 'vars' => ['unit'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'booking.confirmed' => ['category' => 'booking', 'label' => 'Séjour confirmé', 'vars' => ['unit'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'subscription.requested' => ['category' => 'commercial', 'label' => 'Demande de formule (équipe)', 'vars' => [], 'defaults' => $mail, 'mandatory' => false, 'audience' => 'admin'],
        'subscription.activated' => ['category' => 'commercial', 'label' => 'Formule activée', 'vars' => ['plan', 'ends_at'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'subscription.suspended' => ['category' => 'commercial', 'label' => 'Formule suspendue', 'vars' => [], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'subscription.expiring' => ['category' => 'commercial', 'label' => 'Formule bientôt expirée', 'vars' => ['days', 'ends_at'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'subscription.expired' => ['category' => 'commercial', 'label' => 'Formule expirée', 'vars' => ['grace_until'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'subscription.downgrade_enforced' => ['category' => 'commercial', 'label' => 'Annonces mises en pause', 'vars' => ['listings_paused'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'premium.requested' => ['category' => 'commercial', 'label' => 'Demande de mise en avant (équipe)', 'vars' => ['listing'], 'defaults' => $mail, 'mandatory' => false, 'audience' => 'admin'],
        'premium.activated' => ['category' => 'commercial', 'label' => 'Mise en avant activée', 'vars' => ['listing', 'ends_at'], 'defaults' => $on, 'mandatory' => false, 'audience' => 'all'],
        'premium.expired' => ['category' => 'commercial', 'label' => 'Mise en avant terminée', 'vars' => ['listing'], 'defaults' => $push, 'mandatory' => false, 'audience' => 'all'],
    ],
];
