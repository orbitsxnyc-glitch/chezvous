<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentIntent;
use App\Services\Commercial\SettingsService;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Encaissement manuel : le voyageur règle hors de la plateforme (Wave, Orange Money, virement, espèces) en rappelant
 * la référence du séjour ; un administrateur confirme la réception. Aucun webhook, aucun appel à un tiers.
 */
class ManualGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsService $settings) {}

    public function name(): string
    {
        return 'manual';
    }

    public function createPayment(PaymentIntent $intent): array
    {
        return [
            'provider_ref' => 'CV-'.strtoupper(Str::random(8)),
            'instructions' => $this->settings->get('payment_instructions'),
        ];
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function verifyWebhook(string $rawBody, array $headers): array
    {
        throw new RuntimeException('L\'encaissement manuel ne reçoit aucune notification automatique.');
    }

    public function retrievePayment(string $providerRef): ?string
    {
        return null;
    }
}
