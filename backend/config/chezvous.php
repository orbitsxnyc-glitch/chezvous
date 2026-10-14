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

    'admin_seed' => [
        'phone' => env('CHEZVOUS_ADMIN_PHONE'),
        'email' => env('CHEZVOUS_ADMIN_EMAIL'),
        'password' => env('CHEZVOUS_ADMIN_PASSWORD'),
    ],
];
