<?php

namespace Database\Seeders;

use App\Models\Constructor;
use App\Models\Country;
use App\Models\Driver;
use App\Models\FantasyTeamRoster;
use App\Models\Franchise;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Season;
use App\Models\SeasonDriver;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeagueSeeder extends Seeder
{
    public function run(): void
    {
        $franchise = Franchise::where('slug', 'f1')->firstOrFail();
        $season = Season::where('franchise_id', $franchise->id)->where('is_active', true)->firstOrFail();
        $testUser = User::where('email', 'test@example.com')->firstOrFail();

        $league = League::factory()->create([
            'franchise_id' => $franchise->id,
            'season_id' => $season->id,
            'commissioner_id' => $testUser->id,
            'name' => 'Test League',
            'slug' => 'test-league',
            'description' => 'A test league for development.',
            'join_policy' => 'open',
            'draft_completed_at' => now(),
        ]);

        // Observer auto-creates a FantasyTeam when a LeagueMember is created
        LeagueMember::factory()->commissioner()->create([
            'league_id' => $league->id,
            'user_id' => $testUser->id,
        ]);

        $countryIds = Country::inRandomOrder()->limit(22)->pluck('id');

        $drivers = $countryIds->map(fn ($countryId, $i) => Driver::factory()->create([
            'franchise_id' => $franchise->id,
            'country_id' => $countryId,
        ]))->values();
        $constructors = Constructor::factory(11)->create([
            'franchise_id' => $franchise->id,
        ]);

        // Pair 2 drivers per constructor (11 constructors × 2 = 22 drivers)
        $constructors->each(function (Constructor $constructor, int $i) use ($season, $drivers) {
            $pair = $drivers->slice($i * 2, 2);
            foreach ($pair->values() as $j => $driver) {
                SeasonDriver::create([
                    'season_id' => $season->id,
                    'driver_id' => $driver->id,
                    'constructor_id' => $constructor->id,
                    'number' => ($i * 2) + $j + 1,
                    'effective_from' => now(),
                ]);
            }
        });

        $users = User::factory(2)->create();

        foreach ($users as $user) {
            LeagueMember::factory()->create([
                'league_id' => $league->id,
                'user_id' => $user->id,
            ]);
        }

        $allUsers = collect([$testUser, ...$users]);
        $shuffledDrivers = $drivers->shuffle();
        $shuffledConstructors = $constructors->shuffle();

        $allUsers->each(function (User $user, int $index) use ($league, $shuffledDrivers, $shuffledConstructors) {
            $team = $user->fantasyTeams()->where('league_id', $league->id)->firstOrFail();

            // 3 drivers per team (2 in seat, 1 on bench)
            $teamDrivers = $shuffledDrivers->slice($index * 3, 3);
            foreach ($teamDrivers->values() as $i => $driver) {
                FantasyTeamRoster::create([
                    'fantasy_team_id' => $team->id,
                    'entity_type' => 'driver',
                    'entity_id' => $driver->id,
                    'in_seat' => $i < 2,
                    'acquired_at' => now(),
                ]);
            }

            // 1 constructor per team
            FantasyTeamRoster::create([
                'fantasy_team_id' => $team->id,
                'entity_type' => 'constructor',
                'entity_id' => $shuffledConstructors->values()[$index]->id,
                'in_seat' => true,
                'acquired_at' => now(),
            ]);
        });
    }
}
