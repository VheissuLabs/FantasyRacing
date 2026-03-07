<?php

use App\Models\Constructor;
use App\Models\Driver;
use App\Models\Event;
use App\Models\FantasyEventPoint;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamRoster;
use App\Models\Franchise;
use App\Models\FreeAgentPool;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Season;
use App\Models\SeasonConstructor;
use App\Models\SeasonDriver;
use App\Models\Track;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Create a team scenario with a league, an owner, a team with roster entries, and a free agent.
 *
 * @return array{league: League, franchise: Franchise, season: Season, owner: User, team: FantasyTeam, drivers: Driver[], constructors: Constructor[], freeDriver: Driver}
 */
function createTeamScenario(): array
{
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id, 'year' => 2026]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'rules' => ['no_duplicates' => true],
    ]);

    LeagueMember::create(['league_id' => $league->id, 'user_id' => $commissioner->id, 'role' => 'commissioner', 'joined_at' => now()]);

    $owner = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $owner->id, 'role' => 'member', 'joined_at' => now()]);

    $team = FantasyTeam::where('league_id', $league->id)->where('user_id', $owner->id)->first();

    $constructor = Constructor::create([
        'franchise_id' => $franchise->id,
        'name' => 'Test Constructor',
        'slug' => 'test-constructor-' . uniqid(),
        'is_active' => true,
    ]);
    SeasonConstructor::create(['season_id' => $season->id, 'constructor_id' => $constructor->id]);

    $drivers = [];
    for ($i = 0; $i < 3; $i++) {
        $driver = Driver::create([
            'franchise_id' => $franchise->id,
            'name' => "Test Driver {$i}",
            'slug' => "test-driver-{$i}-" . uniqid(),
            'is_active' => true,
        ]);
        SeasonDriver::create([
            'season_id' => $season->id,
            'driver_id' => $driver->id,
            'constructor_id' => $constructor->id,
            'number' => $i + 1,
            'effective_from' => '2026-01-01',
        ]);
        $drivers[] = $driver;
    }

    // Roster: driver 0 seated, driver 1 seated, driver 2 on bench
    FantasyTeamRoster::create(['fantasy_team_id' => $team->id, 'entity_type' => 'constructor', 'entity_id' => $constructor->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team->id, 'entity_type' => 'driver', 'entity_id' => $drivers[0]->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team->id, 'entity_type' => 'driver', 'entity_id' => $drivers[1]->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team->id, 'entity_type' => 'driver', 'entity_id' => $drivers[2]->id, 'in_seat' => false, 'acquired_at' => now()]);

    // Free agent driver
    $freeDriver = Driver::create([
        'franchise_id' => $franchise->id,
        'name' => 'Free Agent Driver',
        'slug' => 'free-agent-driver-' . uniqid(),
        'is_active' => true,
    ]);
    SeasonDriver::create([
        'season_id' => $season->id,
        'driver_id' => $freeDriver->id,
        'constructor_id' => $constructor->id,
        'number' => 99,
        'effective_from' => '2026-01-01',
    ]);
    FreeAgentPool::create([
        'league_id' => $league->id,
        'entity_type' => 'driver',
        'entity_id' => $freeDriver->id,
        'added_at' => now(),
    ]);

    return ['league' => $league, 'franchise' => $franchise, 'season' => $season, 'owner' => $owner, 'team' => $team, 'drivers' => $drivers, 'freeDriver' => $freeDriver, 'constructors' => [$constructor], 'commissioner' => $commissioner];
}

// ============================================================================
// Show
// ============================================================================

test('team show page requires authentication', function () {
    $scenario = createTeamScenario();

    get(route('leagues.teams.show', [$scenario['league']->slug, $scenario['team']->id]))
        ->assertRedirect(route('login'));
});

test('team show page renders with correct props', function () {
    $scenario = createTeamScenario();

    actingAs($scenario['owner'])
        ->get(route('leagues.teams.show', [$scenario['league']->slug, $scenario['team']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Team/Show')
            ->has('league')
            ->has('team')
            ->has('pointsByEvent')
            ->has('totalPoints')
            ->has('freeAgents')
            ->where('isOwner', true)
        );
});

test('team show page 404s when team does not belong to league', function () {
    $scenario = createTeamScenario();
    $otherLeague = League::factory()->create();

    actingAs($scenario['owner'])
        ->get(route('leagues.teams.show', [$otherLeague->slug, $scenario['team']->id]))
        ->assertNotFound();
});

test('team show page identifies owner correctly when viewing own team', function () {
    $scenario = createTeamScenario();

    actingAs($scenario['owner'])
        ->get(route('leagues.teams.show', [$scenario['league']->slug, $scenario['team']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isOwner', true)
        );
});

test('team show page identifies non-owner correctly', function () {
    $scenario = createTeamScenario();
    $otherUser = User::factory()->create();
    LeagueMember::create(['league_id' => $scenario['league']->id, 'user_id' => $otherUser->id, 'role' => 'member', 'joined_at' => now()]);

    actingAs($otherUser)
        ->get(route('leagues.teams.show', [$scenario['league']->slug, $scenario['team']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isOwner', false)
        );
});

// ============================================================================
// Swap Roster
// ============================================================================

test('swap roster succeeds for team owner', function () {
    $scenario = createTeamScenario();

    actingAs($scenario['owner'])
        ->post(route('leagues.teams.swap', [$scenario['league']->slug, $scenario['team']->id]), [
            'bench_driver_id' => $scenario['drivers'][2]->id,
            'in_seat_driver_id' => $scenario['drivers'][0]->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Roster updated.');

    // Bench driver should now be seated
    expect(FantasyTeamRoster::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_id', $scenario['drivers'][2]->id)
        ->where('in_seat', true)->exists())->toBeTrue();

    // Previously seated driver should now be on bench
    expect(FantasyTeamRoster::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->where('in_seat', false)->exists())->toBeTrue();
});

test('swap roster is forbidden for non-owner', function () {
    $scenario = createTeamScenario();
    $otherUser = User::factory()->create();

    actingAs($otherUser)
        ->post(route('leagues.teams.swap', [$scenario['league']->slug, $scenario['team']->id]), [
            'bench_driver_id' => $scenario['drivers'][2]->id,
            'in_seat_driver_id' => $scenario['drivers'][0]->id,
        ])
        ->assertForbidden();
});

test('swap roster 404s when team does not belong to league', function () {
    $scenario = createTeamScenario();
    $otherLeague = League::factory()->create();

    actingAs($scenario['owner'])
        ->post(route('leagues.teams.swap', [$otherLeague->slug, $scenario['team']->id]), [
            'bench_driver_id' => $scenario['drivers'][2]->id,
            'in_seat_driver_id' => $scenario['drivers'][0]->id,
        ])
        ->assertNotFound();
});

// ============================================================================
// Pickup Free Agent
// ============================================================================

test('free agent pickup succeeds for team owner', function () {
    $scenario = createTeamScenario();

    actingAs($scenario['owner'])
        ->post(route('leagues.teams.pickup', [$scenario['league']->slug, $scenario['team']->id]), [
            'entity_type' => 'driver',
            'pickup_entity_id' => $scenario['freeDriver']->id,
            'drop_entity_id' => $scenario['drivers'][2]->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Roster updated.');

    // Free agent should now be on the roster
    expect(FantasyTeamRoster::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_id', $scenario['freeDriver']->id)->exists())->toBeTrue();

    // Dropped driver should be in free agent pool
    expect(FreeAgentPool::where('league_id', $scenario['league']->id)
        ->where('entity_id', $scenario['drivers'][2]->id)->exists())->toBeTrue();
});

test('free agent pickup is forbidden for non-owner', function () {
    $scenario = createTeamScenario();
    $otherUser = User::factory()->create();

    actingAs($otherUser)
        ->post(route('leagues.teams.pickup', [$scenario['league']->slug, $scenario['team']->id]), [
            'entity_type' => 'driver',
            'pickup_entity_id' => $scenario['freeDriver']->id,
            'drop_entity_id' => $scenario['drivers'][2]->id,
        ])
        ->assertForbidden();
});

test('free agent pickup validates required fields', function () {
    $scenario = createTeamScenario();

    actingAs($scenario['owner'])
        ->post(route('leagues.teams.pickup', [$scenario['league']->slug, $scenario['team']->id]), [])
        ->assertSessionHasErrors(['entity_type', 'pickup_entity_id', 'drop_entity_id']);
});

test('team show page includes points breakdown per event', function () {
    $scenario = createTeamScenario();

    $track = Track::create([
        'franchise_id' => $scenario['franchise']->id,
        'name' => 'Test Track',
        'location' => 'Test City',
        'country' => 'Testland',
    ]);

    $event = Event::factory()->completed()->create([
        'season_id' => $scenario['season']->id,
        'track_id' => $track->id,
    ]);

    FantasyEventPoint::create([
        'fantasy_team_id' => $scenario['team']->id,
        'event_id' => $event->id,
        'entity_type' => 'driver',
        'entity_id' => $scenario['drivers'][0]->id,
        'points' => 25.00,
        'breakdown' => ['qualifying' => 10, 'race' => 15],
        'computed_at' => now(),
    ]);

    FantasyEventPoint::create([
        'fantasy_team_id' => $scenario['team']->id,
        'event_id' => $event->id,
        'entity_type' => 'constructor',
        'entity_id' => $scenario['constructors'][0]->id,
        'points' => 15.00,
        'breakdown' => ['qualifying' => 5, 'race' => 10],
        'computed_at' => now(),
    ]);

    actingAs($scenario['owner'])
        ->get(route('leagues.teams.show', [$scenario['league']->slug, $scenario['team']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Team/Show')
            ->has('pointsByEvent', 1)
            ->where('totalPoints', 40)
            ->has('pointsByEvent.0.breakdown', 2)
            ->where('pointsByEvent.0.breakdown.0.entity_type', 'constructor')
            ->where('pointsByEvent.0.breakdown.0.points', 15)
            ->where('pointsByEvent.0.breakdown.1.entity_type', 'driver')
            ->where('pointsByEvent.0.breakdown.1.points', 25)
        );
});
