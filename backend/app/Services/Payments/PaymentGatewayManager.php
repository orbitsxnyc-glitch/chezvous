<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use InvalidArgumentException;

/**
 * Résout l'adaptateur actif (config `payments.driver`) ou celui d'un prestataire nommé. Ajouter Wave ou Orange Money =
 * écrire un adaptateur qui implémente PaymentGateway et l'enregistrer dans `config/payments.php`.
 */
class PaymentGatewayManager
{
    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= (string) config('payments.driver');
        $class = config("payments.gateways.{$name}");
        if (! is_string($class)) {
            throw new InvalidArgumentException("Prestataire de paiement inconnu : {$name}");
        }

        return app($class);
    }
}
