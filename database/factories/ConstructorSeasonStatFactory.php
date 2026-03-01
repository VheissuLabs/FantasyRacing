<?php

namespace Database\Factories;

use App\Models\Constructor;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConstructorSeasonStat>
 */
class ConstructorSeasonStatFactory extends Factory
{
    public function definition(): array
    {
        $wins = fake()->numberBetween(0, 8);
        $podiums = $wins + fake()->numberBetween(0, 8);

        return [
            'constructor_id' => Constructor::factory(),
            'season_id' => Season::factory(),
            'races_entered' => fake()->numberBetween(10, 22),
            'wins' => $wins,
            'podiums' => $podiums,
            'one_twos' => fake()->numberBetween(0, $wins),
            'poles' => fake()->numberBetween(0, 8),
            'fastest_laps' => fake()->numberBetween(0, 5),
            'sprint_wins' => fake()->numberBetween(0, 3),
            'sprint_podiums' => fake()->numberBetween(0, 5),
            'sprint_one_twos' => fake()->numberBetween(0, 2),
            'points_total' => fake()->randomFloat(2, 0, 800),
            'best_finish' => fake()->numberBetween(1, 5),
            'championship_position' => fake()->numberBetween(1, 10),
            'fantasy_points_total' => fake()->randomFloat(2, 0, 600),
            'fantasy_ownership_pct' => fake()->randomFloat(2, 0, 100),
        ];
    }
}
