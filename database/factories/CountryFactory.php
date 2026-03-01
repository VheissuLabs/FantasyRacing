<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->country(),
            'iso2' => fake()->unique()->countryCode(),
            'iso3' => fake()->unique()->countryISOAlpha3(),
            'nationality' => fake()->country(),
            'region' => fake()->randomElement(['Africa', 'Americas', 'Asia', 'Europe', 'Oceania']),
            'subregion' => fake()->word(),
            'emoji' => null,
        ];
    }
}
