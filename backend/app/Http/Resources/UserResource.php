<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'account_type' => $this->account_type->value,
            'status' => $this->status->value,
            'locale' => $this->locale,
            'city' => $this->city,
            'phone_verified' => $this->hasVerifiedPhone(),
            'roles' => $this->getRoleNames(),
            'notification_preferences' => $this->notification_preferences,
        ];
    }
}
