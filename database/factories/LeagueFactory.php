<?php

namespace Database\Factories;

use App\Models\Franchise;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\League>
 */
class LeagueFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'season_id' => Season::factory(),
            'franchise_id' => Franchise::factory(),
            'commissioner_id' => User::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'max_teams' => null,
            'rules' => ['no_duplicates' => true, 'trade_approval_required' => true],
            'invite_code' => null,
            'visibility' => 'public',
            'join_policy' => 'open',
            'is_active' => true,
            'draft_completed_at' => null,
        ];
    }

    public function privateVisibility(): static
    {
        return $this->state(['visibility' => 'private']);
    }

    public function inviteOnly(): static
    {
        return $this->state([
            'join_policy' => 'invite_only',
            'invite_code' => Str::random(8),
        ]);
    }

    public function requestPolicy(): static
    {
        return $this->state(['join_policy' => 'request']);
    }

    public function full(int $max = 2): static
    {
        return $this->state(['max_teams' => $max]);
    }
}
