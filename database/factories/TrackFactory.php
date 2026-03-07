<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Franchise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Track>
 */
class TrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'franchise_id' => Franchise::factory(),
            'country_id' => Country::factory(),
            'name' => fake()->city() . ' Circuit',
            'location' => fake()->city(),
            'country' => fake()->country(),
        ];
    }
}
