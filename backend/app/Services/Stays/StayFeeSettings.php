<?php

namespace App\Services\Stays;

use App\Services\Commercial\SettingsService;

/**
 * Frais de service et taxes applicables aux devis. Réglage en base `stay_fees` :
 * {"service_fee_bps": 700, "taxes": [{"code": "tourist_tax", "label": "Taxe de séjour", "per_night_xof": 500}, {"code": "vat", "label": "TVA", "bps": 1800}]}.
 * Sans réglage, repli sur la configuration (aucune taxe). Les taux légaux sont à valider avec un conseil juridique avant activation.
 */
class StayFeeSettings
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array{service_fee_bps: int, taxes: list<array{code: string, label: string, bps?: int, per_night_xof?: int}>}
     */
    public function current(): array
    {
        $s = $this->settings->get('stay_fees') ?? [];

        return [
            'service_fee_bps' => (int) ($s['service_fee_bps'] ?? config('chezvous.stays.service_fee_bps')),
            'taxes' => array_values($s['taxes'] ?? []),
        ];
    }
}
