<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeagueInvite>
 */
class LeagueInviteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->safeEmail(),
            'token' => Str::random(32),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'accepted_by' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_by' => User::factory(),
        ]);
    }
}
