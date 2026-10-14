<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+22177'.fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password-de-test'),
            'phone_verified_at' => now(),
            'account_type' => AccountType::Individual,
            'status' => UserStatus::Active,
            'locale' => 'fr',
            'remember_token' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['phone_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }
}
