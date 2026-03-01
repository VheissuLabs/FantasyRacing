<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeagueMember>
 */
class LeagueMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
            'joined_at' => now(),
        ];
    }

    public function commissioner(): static
    {
        return $this->state(['role' => 'commissioner']);
    }
}
