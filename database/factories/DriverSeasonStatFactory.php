<?php

namespace Database\Factories;

use App\Models\Constructor;
use App\Models\Driver;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DriverSeasonStat>
 */
class DriverSeasonStatFactory extends Factory
{
    public function definition(): array
    {
        $racesEntered = fake()->numberBetween(10, 22);
        $wins = fake()->numberBetween(0, 5);
        $podiums = $wins + fake()->numberBetween(0, 5);

        return [
            'driver_id' => Driver::factory(),
            'season_id' => Season::factory(),
            'constructor_id' => Constructor::factory(),
            'races_entered' => $racesEntered,
            'races_classified' => $racesEntered - fake()->numberBetween(0, 3),
            'wins' => $wins,
            'podiums' => $podiums,
            'poles' => fake()->numberBetween(0, 5),
            'fastest_laps' => fake()->numberBetween(0, 3),
            'dnfs' => fake()->numberBetween(0, 3),
            'sprint_wins' => fake()->numberBetween(0, 2),
            'sprint_podiums' => fake()->numberBetween(0, 3),
            'sprint_fastest_laps' => fake()->numberBetween(0, 2),
            'points_total' => fake()->randomFloat(2, 0, 400),
            'best_finish' => fake()->numberBetween(1, 10),
            'championship_position' => fake()->numberBetween(1, 20),
            'fantasy_points_total' => fake()->randomFloat(2, 0, 500),
            'fantasy_ownership_pct' => fake()->randomFloat(2, 0, 100),
        ];
    }
}
