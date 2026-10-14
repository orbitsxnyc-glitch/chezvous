<?php

namespace App\Http\Resources;

use App\Models\Agency;
use App\Models\AgencyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Vue d'un MEMBRE de l'agence (inclut NINEA, RCCM et l'état de la vérification). Jamais servie au public.
 *
 * @mixin Agency
 */
class AgencyResource extends JsonResource
{
    public function __construct($resource, private readonly ?AgencyMember $member = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latest = $this->verificationRequests()->latest('id')->first();

        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'address' => $this->address,
            'locality_id' => $this->locality_id,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'contact_whatsapp' => $this->contact_whatsapp,
            'logo_url' => $this->logo_path ? Storage::disk(config('chezvous.images.disk'))->url($this->logo_path) : null,
            'zone_ids' => $this->zones()->pluck('localities.id'),
            'ninea' => $this->makeVisible('ninea')->ninea,
            'rccm' => $this->makeVisible('rccm')->rccm,
            'status' => $this->status->value,
            'verified' => $this->isVerified(),
            'verified_at' => $this->verified_at,
            'verification' => $latest ? ['status' => $latest->status->value, 'submitted_at' => $latest->created_at, 'note' => $latest->note] : null,
            'me' => $this->member ? ['role' => $this->member->role->value, 'can_see_all' => $this->member->can_see_all] : null,
        ];
    }
}
