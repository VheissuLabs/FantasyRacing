<?php

namespace Database\Factories;

use App\Models\FantasyTeam;
use App\Models\League;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trade>
 */
class TradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'initiator_team_id' => FantasyTeam::factory(),
            'receiver_team_id' => FantasyTeam::factory(),
            'status' => 'pending',
            'initiated_at' => now(),
            'resolved_at' => null,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => 'rejected',
            'resolved_at' => now(),
        ]);
    }
}
