<?php

namespace App\Http\Resources;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Profil public d'agence (AGC-01). Jamais exposés : NINEA, RCCM, justificatifs, membres, demandes.
 *
 * @mixin Agency
 */
class PublicAgencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'address' => $this->address,
            'locality' => $this->locality?->name,
            'contact' => ['phone' => $this->contact_phone, 'email' => $this->contact_email, 'whatsapp' => $this->contact_whatsapp],
            'logo_url' => $this->logo_path ? Storage::disk(config('chezvous.images.disk'))->url($this->logo_path) : null,
            'zones' => $this->zones->map(fn ($z) => ['name' => $z->name, 'slug' => $z->slug])->values(),
            'verified' => $this->isVerified(),
            'verified_at' => $this->verified_at,
            'notice' => 'Le badge « Agence vérifiée » indique que des justificatifs ont été examinés ; il ne constitue pas une garantie juridique.',
            'active_listings' => $this->active_listings_count ?? 0,
        ];
    }
}
