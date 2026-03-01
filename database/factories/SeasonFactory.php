<?php

namespace Database\Factories;

use App\Models\Franchise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Season>
 */
class SeasonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'franchise_id' => Franchise::factory(),
            'name' => (string) fake()->year(),
            'year' => fake()->year(),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}
