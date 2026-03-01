<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FantasyTeam>
 */
class FantasyTeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(2, true).' Racing',
        ];
    }
}
