<?php

use App\Services\Payments\ManualGateway;

/**
 * Prestataires de paiement (règle 4). Aucun prestataire en ligne n'est actif tant que le porteur du projet n'a pas ouvert
 * de compte marchand (Wave, Orange Money) : seul l'encaissement manuel confirmé par un administrateur existe.
 */
return [
    'driver' => env('CHEZVOUS_PAYMENT_DRIVER', 'manual'),
    'gateways' => [
        'manual' => ManualGateway::class,
    ],
];
