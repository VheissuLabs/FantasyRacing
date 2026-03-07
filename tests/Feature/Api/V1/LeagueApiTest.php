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

beforeEach(function () {
    // The API routes use auth:sanctum but Sanctum is not installed.
    // Register the sanctum guard as a session-based guard so tests can run.
    config()->set('auth.guards.sanctum', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
});

test('api index returns public active leagues', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/leagues')
        ->assertOk()
        ->assertJsonPath('data.0.id', $league->id);
});

test('api index excludes private and inactive leagues', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'private',
        'is_active' => true,
    ]);

    League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'is_active' => false,
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/leagues')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('api index filters by franchise slug', function () {
    $franchiseA = Franchise::factory()->create();
    $seasonA = Season::factory()->create(['franchise_id' => $franchiseA->id]);

    $franchiseB = Franchise::factory()->create();
    $seasonB = Season::factory()->create(['franchise_id' => $franchiseB->id]);

    $commissioner = User::factory()->create();

    $leagueA = League::factory()->create([
        'franchise_id' => $franchiseA->id,
        'season_id' => $seasonA->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    League::factory()->create([
        'franchise_id' => $franchiseB->id,
        'season_id' => $seasonB->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/leagues?franchise=' . $franchiseA->slug)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $leagueA->id);
});

test('api index filters by season year', function () {
    $franchise = Franchise::factory()->create();
    $season2025 = Season::factory()->create(['franchise_id' => $franchise->id, 'year' => 2025]);
    $season2026 = Season::factory()->create(['franchise_id' => $franchise->id, 'year' => 2026]);
    $commissioner = User::factory()->create();

    $league2025 = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season2025->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season2026->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/leagues?season=2025')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $league2025->id);
});

test('api show returns league data', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/leagues/' . $league->slug)
        ->assertOk()
        ->assertJsonPath('id', $league->id)
        ->assertJsonPath('name', $league->name)
        ->assertJsonStructure(['franchise', 'season', 'commissioner', 'members_count']);
});

test('api show blocks private league for unauthenticated user', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'private',
    ]);

    $this->getJson('/api/v1/leagues/' . $league->slug)
        ->assertUnauthorized();
});

test('api show allows private league for authenticated user', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'private',
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/leagues/' . $league->slug)
        ->assertOk()
        ->assertJsonPath('id', $league->id);
});

test('api teams returns teams with rosters', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
    ]);

    $team = FantasyTeam::factory()->create(['league_id' => $league->id]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/leagues/' . $league->slug . '/teams')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $team->id)
        ->assertJsonStructure(['0' => ['id', 'name', 'user', 'roster']]);
});

test('api teams blocks private league for unauthenticated user', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'private',
    ]);

    $this->getJson('/api/v1/leagues/' . $league->slug . '/teams')
        ->assertUnauthorized();
});

test('standings api returns ranked standings', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
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
        'points' => 30,
        'breakdown' => [],
        'computed_at' => now(),
    ]);

    FantasyEventPoint::create([
        'fantasy_team_id' => $teamB->id,
        'event_id' => Event::factory()->create(['season_id' => $season->id, 'track_id' => $track->id])->id,
        'entity_type' => 'driver',
        'entity_id' => 2,
        'points' => 75,
        'breakdown' => [],
        'computed_at' => now(),
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/leagues/' . $league->slug . '/standings')
        ->assertOk()
        ->assertJsonPath('league_id', $league->id)
        ->assertJsonPath('league_name', $league->name)
        ->assertJsonCount(2, 'standings')
        ->assertJsonPath('standings.0.rank', 1)
        ->assertJsonPath('standings.0.team_name', 'Team B')
        ->assertJsonPath('standings.0.total_points', 75)
        ->assertJsonPath('standings.1.rank', 2)
        ->assertJsonPath('standings.1.team_name', 'Team A')
        ->assertJsonPath('standings.1.total_points', 30);
});

test('standings api blocks private league for unauthenticated user', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'private',
    ]);

    $this->getJson('/api/v1/leagues/' . $league->slug . '/standings')
        ->assertUnauthorized();
});
