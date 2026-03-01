<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Franchise;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->name('male');

        return [
            'franchise_id' => Franchise::factory(),
            'country_id' => Country::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
