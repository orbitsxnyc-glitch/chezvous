<?php

namespace App\Http\Resources;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\Inquiries\InquiryAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue d'une demande ADAPTÉE au lecteur :
 * - le demandeur voit sa demande, l'annonceur comme « agence » ou « annonceur », jamais de téléphone d'annonceur ;
 * - le côté annonceur voit le téléphone du demandeur (DEM-06) et les notes internes ;
 * - personne d'autre n'y a accès (la Policy répond 404 avant d'arriver ici).
 *
 * @mixin Inquiry
 */
class InquiryResource extends JsonResource
{
    public function __construct($resource, private readonly bool $detail = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $access = app(InquiryAccess::class);
        $isRequester = $access->isRequester($viewer, $this->resource);
        $ownerSide = ! $isRequester && $access->ownerSide($viewer, $this->resource) !== InquiryAccess::NONE;
        $listing = $this->listing;

        $data = [
            'id' => $this->ulid,
            'role' => $isRequester ? 'requester' : 'owner',
            'status' => $this->status->value,
            'listing' => ['id' => $listing->ulid, 'reference' => $listing->reference, 'title' => $listing->title],
            'preferred_channel' => $this->preferred_channel->value,
            'created_at' => $this->created_at,
            'last_activity_at' => $this->last_activity_at,
            'first_response_at' => $this->first_response_at,
            'unread' => $this->messages()->whereNull('read_at')->when($isRequester, fn ($q) => $q->where('sender_id', '!=', $viewer->id), fn ($q) => $q->where('sender_id', $this->requester_id))->count(),
        ];

        if ($ownerSide) {
            // Coordonnées du demandeur : réservées au côté annonceur autorisé.
            $data['requester'] = ['name' => $this->requester->name, 'phone' => $this->requester_phone];
            $data['assigned_to'] = $this->agency_id && $this->assignee ? ['name' => $this->assignee->name] : null;
            $data['agency'] = $this->agency_id ? ['name' => $this->agency->name] : null;
            $data['can_respond'] = $access->ownerSide($viewer, $this->resource) === InquiryAccess::RESPOND;
        } else {
            $data['advertiser'] = ['name' => $this->agency_id ? $this->agency->name : 'Annonceur'];
        }

        if ($this->detail) {
            $data['messages'] = $this->messages->map(fn ($m) => [
                'id' => $m->id, 'mine' => $m->sender_id === $viewer->id, 'body' => $m->body, 'created_at' => $m->created_at, 'read_at' => $m->read_at,
                // L'annonceur est anonyme pour le demandeur ; le côté annonceur voit les noms.
                'from' => $m->sender_id === $this->requester_id ? 'requester' : 'owner',
                'sender_name' => $ownerSide ? $m->sender->name : null,
            ])->values();
            $data['visits'] = $this->visits->map(fn ($v) => (new VisitResource($v, $this->resource))->toArray($request))->values();
            if ($ownerSide) {
                $data['notes'] = $this->notes->map(fn ($n) => ['id' => $n->id, 'body' => $n->body, 'author' => $n->author->name, 'created_at' => $n->created_at])->values();
            }
        }

        return $data;
    }
}
