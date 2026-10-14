<?php

return [
    // Aucune valeur métier en dur : tout se règle ici ou par variables d'environnement.
    'otp' => [
        'driver' => env('CHEZVOUS_OTP_DRIVER', 'log'), // log (développement) | mail
        'length' => (int) env('CHEZVOUS_OTP_LENGTH', 6),
        'ttl_minutes' => (int) env('CHEZVOUS_OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('CHEZVOUS_OTP_MAX_ATTEMPTS', 5),
    ],

    'rate_limits' => [
        'login_per_minute' => (int) env('CHEZVOUS_LOGIN_PER_MINUTE', 5),
        'otp_per_minute' => (int) env('CHEZVOUS_OTP_PER_MINUTE', 3),
        'otp_verify_per_minute' => (int) env('CHEZVOUS_OTP_VERIFY_PER_MINUTE', 10),
        'otp_per_hour_ip' => (int) env('CHEZVOUS_OTP_PER_HOUR_IP', 10),
        'uploads_per_hour' => (int) env('CHEZVOUS_UPLOADS_PER_HOUR', 200),
        'reports_per_hour' => (int) env('CHEZVOUS_REPORTS_PER_HOUR', 20),
        'commercial_requests_per_hour' => (int) env('CHEZVOUS_COMMERCIAL_REQUESTS_PER_HOUR', 20),
        'inquiries_per_hour' => (int) env('CHEZVOUS_INQUIRIES_PER_HOUR', 20),
        'messages_per_hour' => (int) env('CHEZVOUS_MESSAGES_PER_HOUR', 120),
        'invitations_per_hour' => (int) env('CHEZVOUS_INVITATIONS_PER_HOUR', 30),
        'register_per_hour_ip' => (int) env('CHEZVOUS_REGISTER_PER_HOUR_IP', 10),
    ],

    'phone' => [
        'country_code' => '221',
        // Après l'indicatif : 9 chiffres commençant par 3 (fixe) ou 7 (mobile).
        'national_pattern' => '/^[37]\d{8}$/',
    ],

    'listings' => [
        'quota_individual' => (int) env('CHEZVOUS_QUOTA_INDIVIDUAL', 3), // annonces actives simultanées d'un particulier (H6)
        'expiry_days' => (int) env('CHEZVOUS_LISTING_EXPIRY_DAYS', 60),
        'expiry_reminder_days' => (int) env('CHEZVOUS_LISTING_REMINDER_DAYS', 7),
        'closed_visible_days' => (int) env('CHEZVOUS_CLOSED_VISIBLE_DAYS', 30),
        // Nombre d'annonces déjà approuvées par un modérateur après lequel un compte est publié sans modération (H7).
        'trusted_after_approvals' => (int) env('CHEZVOUS_TRUSTED_AFTER_APPROVALS', 1),
        'max_images' => (int) env('CHEZVOUS_MAX_IMAGES', 20),
        'max_image_kb' => (int) env('CHEZVOUS_MAX_IMAGE_KB', 10240),
        // Fraîcheur de la disponibilité (heures depuis la dernière confirmation).
        'freshness_reconfirm_hours' => (int) env('CHEZVOUS_FRESHNESS_RECONFIRM_HOURS', 48),
        'freshness_stale_hours' => (int) env('CHEZVOUS_FRESHNESS_STALE_HOURS', 168),
    ],

    'commercial' => [
        // Droits de la formule gratuite si la ligne n'existe pas en base. Les prix et quotas se règlent en base (table plans).
        // Les quotas d'annonces gratuits restent ceux de chezvous.listings.quota_individual et chezvous.agencies.quota_listings.
        'free' => ['agency_agents' => (int) env('CHEZVOUS_FREE_AGENCY_AGENTS', 5), 'featured_credits' => 0],
        'downgrade_grace_days' => (int) env('CHEZVOUS_DOWNGRADE_GRACE_DAYS', 7), // délai pour choisir les annonces à garder après une expiration
        'expiry_reminder_days' => [7, 1],
        'featured_display_cap' => (int) env('CHEZVOUS_FEATURED_DISPLAY_CAP', 6), // « À la une » affichées en même temps
        'featured_rotation_hours' => (int) env('CHEZVOUS_FEATURED_ROTATION_HOURS', 6),
        'proof_max_kb' => (int) env('CHEZVOUS_PROOF_MAX_KB', 5120),
        'proof_allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png'],
        'proof_link_minutes' => (int) env('CHEZVOUS_PROOF_LINK_MINUTES', 5),
        'entitlements_cache_seconds' => (int) env('CHEZVOUS_ENTITLEMENTS_CACHE', 600),
    ],

    'inquiries' => [
        'message_max' => (int) env('CHEZVOUS_MESSAGE_MAX', 2000),
        'unanswered_hours' => (int) env('CHEZVOUS_UNANSWERED_HOURS', 48),
        'response_window_days' => (int) env('CHEZVOUS_RESPONSE_WINDOW_DAYS', 90), // fenêtre du taux de réponse
        'visit' => [
            'slots_max' => 3,
            'min_lead_minutes' => (int) env('CHEZVOUS_VISIT_MIN_LEAD_MINUTES', 60),
            'max_days_ahead' => (int) env('CHEZVOUS_VISIT_MAX_DAYS', 60),
            'duration_minutes' => (int) env('CHEZVOUS_VISIT_DURATION_MINUTES', 45), // créneaux plus proches = conflit
            'reminder_hours' => [24, 2],
        ],
        'favorites_max' => (int) env('CHEZVOUS_FAVORITES_MAX', 200),
        'saved_searches_max' => (int) env('CHEZVOUS_SAVED_SEARCHES_MAX', 10),
        'alerts_per_day' => (int) env('CHEZVOUS_ALERTS_PER_DAY', 20),
        'daily_alert_hour' => (int) env('CHEZVOUS_DAILY_ALERT_HOUR', 8),
    ],

    'agencies' => [
        'quota_listings' => (int) env('CHEZVOUS_QUOTA_AGENCY', 50), // annonces actives simultanées d'une agence (remplacé par la formule en Phase 6)
        'invitation_ttl_days' => (int) env('CHEZVOUS_INVITATION_TTL_DAYS', 7),
        'invitation_driver' => env('CHEZVOUS_INVITATION_DRIVER', 'log'), // log (développement) | mail
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'verification_max_kb' => (int) env('CHEZVOUS_VERIFICATION_MAX_KB', 5120),
        'verification_allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png'],
        'document_link_minutes' => (int) env('CHEZVOUS_DOCUMENT_LINK_MINUTES', 5), // durée de vie d'une URL signée de justificatif
    ],

    'search' => [
        'per_page' => (int) env('CHEZVOUS_SEARCH_PER_PAGE', 20),
        'per_minute' => (int) env('CHEZVOUS_SEARCH_PER_MINUTE', 120),
        'hide_stale' => (bool) env('CHEZVOUS_HIDE_STALE', false), // false : les biens à information ancienne sont rétrogradés, pas masqués
        'similar_price_tolerance' => 0.25,
        'similar_limit' => 6,
        'map_limit' => 300,
        'sitemap_limit' => 5000,
    ],

    'counters' => [
        'dedupe_ttl_hours' => 36,
    ],

    'images' => [
        'disk' => env('CHEZVOUS_IMAGES_DISK', 'public'), // médias publics traités ; passer à un disque S3 plus tard
        'originals_disk' => 'local', // privé : le fichier brut n'y reste que le temps du traitement
        'sizes' => ['thumb' => 320, 'medium' => 800, 'large' => 1600], // plus grand côté, en pixels
        'quality' => 82,
        'max_pixels' => 40_000_000, // protection contre les images « bombes de décompression »
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'location' => [
        // Cadre englobant approximatif du Sénégal, pour détecter une coordonnée aberrante.
        'approx_radius_m' => (int) env('CHEZVOUS_APPROX_RADIUS_M', 300), // décalage maximal de la position « approximative »
        'bounds' => ['lat_min' => 12.0, 'lat_max' => 16.8, 'lon_min' => -17.7, 'lon_max' => -11.3],
    ],

    'admin_seed' => [
        'phone' => env('CHEZVOUS_ADMIN_PHONE'),
        'email' => env('CHEZVOUS_ADMIN_EMAIL'),
        'password' => env('CHEZVOUS_ADMIN_PASSWORD'),
    ],
];
