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
     * Dernier contrôle de chaque type pour le bien et son annonceur ; n'est affiché que s'il est réussi et encore valide.
     * Un contrôle échoué ou expiré masque donc un ancien succès : un résultat périmé ne doit jamais rassurer.
     *
     * @return list<array{type: string, status: string, performed_at: string|null}>
     */
    public function checks(Property $property, Listing $listing): array
    {
        $rows = DB::table('verification_checks')
            ->where(function ($q) use ($property, $listing) {
                $q->where(fn ($w) => $w->where('subject_type', 'property')->where('subject_id', $property->id))
                    ->orWhere(fn ($w) => $w->where('subject_type', 'user')->where('subject_id', $listing->advertiser_user_id))
                    ->orWhere(fn ($w) => $w->where('subject_type', 'mandate')->whereIn('subject_id', $property->mandates()->select('id')))
                    ->when($listing->agency_id, fn ($q2) => $q2->orWhere(fn ($w) => $w->where('subject_type', 'agency')->where('subject_id', $listing->agency_id)));
            })
            ->orderByDesc('performed_at')->orderByDesc('id')->get(['check_type', 'status', 'performed_at', 'valid_until']);

        $latest = [];
        foreach ($rows as $r) {
            $latest[$r->check_type] ??= $r;
        }
        $latest = collect($latest)
            ->filter(fn ($r) => $r->status === CheckStatus::Passed->value && ($r->valid_until === null || $r->valid_until > now()->toDateTimeString()))
            ->map(fn ($r) => ['type' => $r->check_type, 'status' => $r->status, 'performed_at' => $r->performed_at])
            ->all();

        // Le téléphone vérifié de l'annonceur (inscription par OTP) est un contrôle de téléphone réussi.
        // Un compte vérifié par e-mail n'a PAS de numéro prouvé : aucun contrôle « téléphone » n'est affiché pour lui.
        $phoneVerifiedAt = $listing->advertiser?->hasProvenPhone() ? $listing->advertiser->phone_verified_at : null;
        if ($phoneVerifiedAt !== null && ! isset($latest['phone'])) {
            $latest['phone'] = ['type' => 'phone', 'status' => 'passed', 'performed_at' => $phoneVerifiedAt->toIso8601String()];
        }

        return array_values($latest);
    }
}
