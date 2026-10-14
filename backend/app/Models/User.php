<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\AccountType;
use App\Enums\AgencyStatus;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property AccountType $account_type
 * @property UserStatus $status
 * @property Carbon|null $phone_verified_at
 * @property array<string, mixed>|null $notification_preferences
 */
#[Fillable(['name', 'phone', 'email', 'whatsapp', 'password', 'account_type', 'locale', 'city'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, HasUlids, Notifiable, SoftDeletes;

    /**
     * Seule la colonne `ulid` est un identifiant ULID ; la clé primaire reste un bigint.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'status' => UserStatus::class,
            'notification_preferences' => 'array',
        ];
    }

    /** @return HasOne<AgencyMember, $this> */
    public function agencyMembership(): HasOne
    {
        return $this->hasOne(AgencyMember::class);
    }

    /** Appartenance ACTIVE à une agence active (un membre suspendu n'a plus aucun accès à l'agence). */
    public function activeMembership(): ?AgencyMember
    {
        $member = $this->agencyMembership()->with('agency')->first();

        return $member !== null && $member->isActive() && $member->agency?->status === AgencyStatus::Active ? $member : null;
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
