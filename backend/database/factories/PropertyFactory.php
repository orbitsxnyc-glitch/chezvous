<?php

namespace Database\Factories;

use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'property_type_id' => fn () => PropertyType::firstWhere('code', 'apartment')->id,
            'locality_id' => fn () => Locality::where('level', 'neighborhood')->inRandomOrder()->value('id'),
            'address' => fake()->streetAddress(),
            'surface_m2' => fake()->numberBetween(25, 250),
            'rooms' => 4,
            'bedrooms' => 3,
            'bathrooms' => 2,
        ];
    }
}
