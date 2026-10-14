<?php

namespace App\Services\Notifications\Channels;

/**
 * Résultat d'un envoi.
 * - sent : remis au fournisseur réel ;
 * - stubbed : canal factice (aucun fournisseur) ; le message n'a PAS été envoyé ;
 * - skipped : impossible (pas d'adresse, pas d'appareil, pas de consentement).
 */
final class DeliveryResult
{
    private function __construct(public readonly string $status, public readonly string $provider, public readonly ?string $reason = null) {}

    public static function sent(string $provider): self
    {
        return new self('sent', $provider);
    }

    public static function stubbed(string $provider = 'stub'): self
    {
        return new self('stubbed', $provider);
    }

    public static function skipped(string $reason): self
    {
        return new self('skipped', 'none', $reason);
    }
}
