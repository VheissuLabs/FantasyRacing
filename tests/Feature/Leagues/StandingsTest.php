<?php

use App\Models\Country;
use App\Models\Event;
use App\Models\FantasyEventPoint;
use App\Models\FantasyTeam;
use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use App\Models\Track;
use App\Models\User;

test('standings page requires authentication', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
    ]);

    $this->get(route('leagues.standings', $league->slug))
        ->assertRedirect(route('login'));
});

test('standings page renders with correct component', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('leagues.standings', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Leagues/Standings/Index'));
});

test('standings page shows teams ranked by points', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
    ]);

    $country = Country::create([
        'name' => 'Testland',
        'iso2' => fake()->unique()->lexify('??'),
        'iso3' => fake()->unique()->lexify('???'),
        'nationality' => 'Testlandian',
        'region' => 'Europe',
        'subregion' => 'Western',
    ]);

    $track = Track::factory()->create([
        'franchise_id' => $franchise->id,
        'country_id' => $country->id,
    ]);

    $teamA = FantasyTeam::factory()->create(['league_id' => $league->id, 'name' => 'Team A']);
    $teamB = FantasyTeam::factory()->create(['league_id' => $league->id, 'name' => 'Team B']);

    FantasyEventPoint::create([
        'fantasy_team_id' => $teamA->id,
        'event_id' => Event::factory()->create(['season_id' => $season->id, 'track_id' => $track->id])->id,
        'entity_type' => 'driver',
        'entity_id' => 1,
        'points' => 50,
        'breakdown' => [],
        'computed_at' => now(),
    ]);

    FantasyEventPoint::create([
        'fantasy_team_id' => $teamB->id,
        'event_id' => Event::factory()->create(['season_id' => $season->id, 'track_id' => $track->id])->id,
        'entity_type' => 'driver',
        'entity_id' => 2,
        'points' => 100,
        'breakdown' => [],
        'computed_at' => now(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('leagues.standings', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Standings/Index')
            ->has('standings', 2)
            ->where('standings.0.rank', 1)
            ->where('standings.0.name', 'Team B')
            ->where('standings.0.total_points', fn ($value) => (float) $value === 100.0)
            ->where('standings.1.rank', 2)
            ->where('standings.1.name', 'Team A')
            ->where('standings.1.total_points', fn ($value) => (float) $value === 50.0)
        );
});

test('standings page shows completed events', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
    ]);

    $country = Country::create([
        'name' => 'Testland',
        'iso2' => fake()->unique()->lexify('??'),
        'iso3' => fake()->unique()->lexify('???'),
        'nationality' => 'Testlandian',
        'region' => 'Europe',
        'subregion' => 'Western',
    ]);

    $track = Track::factory()->create([
        'franchise_id' => $franchise->id,
        'country_id' => $country->id,
    ]);

    Event::factory()->completed()->create([
        'season_id' => $season->id,
        'track_id' => $track->id,
        'name' => 'Completed GP',
        'sort_order' => 1,
    ]);

    Event::factory()->create([
        'season_id' => $season->id,
        'track_id' => $track->id,
        'name' => 'Upcoming GP',
        'status' => 'scheduled',
        'sort_order' => 2,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('leagues.standings', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Standings/Index')
            ->has('events', 1)
            ->where('events.0.name', 'Completed GP')
        );
});
