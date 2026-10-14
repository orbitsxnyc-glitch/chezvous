<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Enums\TransactionType;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => fn () => sprintf('CV-%d-%06d', now()->year, DB::selectOne("select nextval('listing_reference_seq') as n")->n),
            'property_id' => Property::factory(),
            'advertiser_user_id' => User::factory(),
            'transaction' => TransactionType::RentLong,
            'title' => 'Appartement F4 à louer',
            'description' => fake()->paragraph(),
            'price_xof' => 450000,
            'deposit_months' => 2,
            'advance_months' => 1,
            'status' => ListingStatus::Draft,
        ];
    }

    public function status(ListingStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
