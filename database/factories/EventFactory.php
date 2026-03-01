<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'track_id' => Track::factory(),
            'name' => fake()->city().' Grand Prix',
            'type' => 'race',
            'scheduled_at' => fake()->dateTimeBetween('now', '+6 months'),
            'status' => 'scheduled',
            'sort_order' => fake()->numberBetween(1, 24),
            'round' => fake()->numberBetween(1, 24),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'scheduled_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    public function locked(): static
    {
        return $this->state([
            'status' => 'locked',
            'locked_at' => now(),
        ]);
    }
}
