<?php

namespace App\Contracts;

use App\Models\PaymentIntent;

/**
 * Interface unique des paiements (règle 4). Un adaptateur par prestataire : « manual » (encaissement confirmé par un admin)
 * aujourd'hui ; Wave et Orange Money quand le porteur aura ouvert un compte marchand.
 *
 * Un paiement n'est JAMAIS réussi parce que le navigateur le dit : seule une confirmation vérifiée côté serveur
 * (webhook signé relu auprès du prestataire, ou administrateur) crée un événement « succeeded ».
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Prépare le règlement : référence à rappeler et consignes affichées au voyageur.
     *
     * @return array{provider_ref: string, instructions: array<string, mixed>|null}
     */
    public function createPayment(PaymentIntent $intent): array;

    /** Les notifications du prestataire sont-elles prises en charge (webhook) ? */
    public function supportsWebhooks(): bool;

    /**
     * Vérifie la signature et l'horodatage d'une notification brute. Lève une exception si elle est invalide ou rejouée.
     *
     * @param  array<string, string>  $headers
     * @return array{external_event_id: string, provider_ref: string, status: string, amount_xof: int, currency: string}
     */
    public function verifyWebhook(string $rawBody, array $headers): array;

    /** Relit le statut du paiement auprès du prestataire (null : non supporté). */
    public function retrievePayment(string $providerRef): ?string;
}
