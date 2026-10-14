<?php

namespace App\Services\Passport;

use App\Enums\CheckStatus;
use App\Models\Listing;
use App\Models\Property;
use Illuminate\Support\Facades\DB;

/**
 * Preuves affichées sur le Passeport du Bien : des contrôles explicites et datés, jamais une note opaque.
 * « Contrôlé » ne signifie pas « propriété juridiquement garantie ».
 */
class PassportService
{
    /**
     * Derniers contrôles réussis et non expirés du bien et de son annonceur, un par type.
     *
     * @return list<array{type: string, status: string, performed_at: string|null}>
     */
    public function checks(Property $property, Listing $listing): array
    {
        $rows = DB::table('verification_checks')
            ->where('status', CheckStatus::Passed->value)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->where(function ($q) use ($property, $listing) {
                $q->where(fn ($w) => $w->where('subject_type', 'property')->where('subject_id', $property->id))
                    ->orWhere(fn ($w) => $w->where('subject_type', 'user')->where('subject_id', $listing->advertiser_user_id))
                    ->orWhere(fn ($w) => $w->where('subject_type', 'mandate')->whereIn('subject_id', $property->mandates()->select('id')));
            })
            ->orderByDesc('performed_at')->get(['check_type', 'status', 'performed_at']);

        $latest = [];
        foreach ($rows as $r) {
            $latest[$r->check_type] ??= ['type' => $r->check_type, 'status' => $r->status, 'performed_at' => $r->performed_at];
        }

        // Le téléphone vérifié de l'annonceur (inscription par OTP) est un contrôle de téléphone réussi.
        $phoneVerifiedAt = $listing->advertiser?->phone_verified_at;
        if ($phoneVerifiedAt !== null && ! isset($latest['phone'])) {
            $latest['phone'] = ['type' => 'phone', 'status' => 'passed', 'performed_at' => $phoneVerifiedAt->toIso8601String()];
        }

        return array_values($latest);
    }
}
