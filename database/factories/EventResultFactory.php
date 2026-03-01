<?php

namespace Database\Factories;

use App\Models\Constructor;
use App\Models\Driver;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventResult>
 */
class EventResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'driver_id' => Driver::factory(),
            'constructor_id' => Constructor::factory(),
            'finish_position' => fake()->numberBetween(1, 20),
            'grid_position' => fake()->numberBetween(1, 20),
            'status' => 'classified',
            'fastest_lap' => false,
            'driver_of_the_day' => false,
            'points_eligible' => true,
        ];
    }

    public function withFastestLap(): static
    {
        return $this->state(['fastest_lap' => true]);
    }

    public function driverOfTheDay(): static
    {
        return $this->state(['driver_of_the_day' => true]);
    }

    public function dnf(): static
    {
        return $this->state([
            'status' => 'dnf',
            'finish_position' => null,
        ]);
    }
}
