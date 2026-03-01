<?php

namespace Database\Factories;

use App\Models\Franchise;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Constructor>
 */
class ConstructorFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'franchise_id' => Franchise::factory(),
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
