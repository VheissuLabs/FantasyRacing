<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeagueJoinRequest>
 */
class LeagueJoinRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'user_id' => User::factory(),
            'message' => fake()->optional()->sentence(),
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
