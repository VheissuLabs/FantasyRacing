<?php

use App\Models\BonusPointsScheme;
use App\Models\Constructor;
use App\Models\Driver;
use App\Models\Event;
use App\Models\EventPitstop;
use App\Models\EventResult;
use App\Models\FantasyEventPoint;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamRoster;
use App\Models\Franchise;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\PointsScheme;
use App\Models\RosterSnapshot;
use App\Models\Season;
use App\Models\SeasonConstructor;
use App\Models\SeasonDriver;
use App\Models\Track;
use App\Models\User;
use App\Services\PointsCalculationService;

/**
 * Set up a complete scenario with franchise, season, league, team, drivers, constructors,
 * points schemes, and bonus points schemes for testing points calculation.
 *
 * @return array{franchise: Franchise, season: Season, league: League, team: FantasyTeam, drivers: Driver[], constructors: Constructor[], user: User, track: Track}
 */
function createPointsScenario(): array
{
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id, 'year' => 2026]);
    $track = Track::create(['franchise_id' => $franchise->id, 'name' => 'Test Circuit', 'location' => 'Test City', 'country' => 'Test Country']);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
    ]);

    LeagueMember::create(['league_id' => $league->id, 'user_id' => $commissioner->id, 'role' => 'commissioner', 'joined_at' => now()]);

    $team = FantasyTeam::factory()->create([
        'league_id' => $league->id,
        'user_id' => $commissioner->id,
    ]);

    // Create constructors and drivers
    $constructors = [];
    $drivers = [];

    for ($i = 0; $i < 2; $i++) {
        $constructor = Constructor::create([
            'franchise_id' => $franchise->id,
            'name' => "Constructor {$i}",
            'slug' => "constructor-{$i}",
            'is_active' => true,
        ]);
        SeasonConstructor::create(['season_id' => $season->id, 'constructor_id' => $constructor->id]);
        $constructors[] = $constructor;

        for ($j = 0; $j < 2; $j++) {
            $driverIndex = $i * 2 + $j;
            $driver = Driver::create([
                'franchise_id' => $franchise->id,
                'name' => "Driver {$driverIndex}",
                'slug' => "driver-{$driverIndex}",
                'is_active' => true,
            ]);
            SeasonDriver::create([
                'season_id' => $season->id,
                'driver_id' => $driver->id,
                'constructor_id' => $constructor->id,
                'number' => $driverIndex + 1,
                'effective_from' => '2026-01-01',
            ]);
            $drivers[] = $driver;
        }
    }

    // Add drivers 0 & 1 to team roster (in seat), driver 2 as bench
    FantasyTeamRoster::create(['fantasy_team_id' => $team->id, 'entity_type' => 'driver', 'entity_id' => $drivers[0]->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team->id, 'entity_type' => 'driver', 'entity_id' => $drivers[1]->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team->id, 'entity_type' => 'driver', 'entity_id' => $drivers[2]->id, 'in_seat' => false, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team->id, 'entity_type' => 'constructor', 'entity_id' => $constructors[0]->id, 'in_seat' => true, 'acquired_at' => now()]);

    return compact('franchise', 'season', 'league', 'team', 'drivers', 'constructors', 'track') + ['user' => $commissioner];
}

/**
 * Seed the standard F1-style position points for a franchise.
 */
function seedPositionPoints(int $franchiseId): void
{
    $racePoints = [1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 4, 9 => 2, 10 => 1];
    $qualifyingPoints = [1 => 10, 2 => 8, 3 => 6, 4 => 5, 5 => 4, 6 => 3, 7 => 2, 8 => 1];
    $sprintPoints = [1 => 8, 2 => 7, 3 => 6, 4 => 5, 5 => 4, 6 => 3, 7 => 2, 8 => 1];

    foreach ($racePoints as $pos => $pts) {
        PointsScheme::create(['franchise_id' => $franchiseId, 'event_type' => 'race', 'position' => $pos, 'points' => $pts]);
    }

    foreach ($qualifyingPoints as $pos => $pts) {
        PointsScheme::create(['franchise_id' => $franchiseId, 'event_type' => 'qualifying', 'position' => $pos, 'points' => $pts]);
    }

    foreach ($sprintPoints as $pos => $pts) {
        PointsScheme::create(['franchise_id' => $franchiseId, 'event_type' => 'sprint', 'position' => $pos, 'points' => $pts]);
    }
}

/**
 * Seed bonus points for a franchise.
 *
 * @param  array<string, array<string, array<string, float>>>  $bonuses  [event_type => [bonus_key => [applies_to => points]]]
 */
function seedBonusPoints(int $franchiseId, array $bonuses): void
{
    foreach ($bonuses as $eventType => $keys) {
        foreach ($keys as $bonusKey => $targets) {
            foreach ($targets as $appliesTo => $points) {
                BonusPointsScheme::create([
                    'franchise_id' => $franchiseId,
                    'event_type' => $eventType,
                    'bonus_key' => $bonusKey,
                    'applies_to' => $appliesTo,
                    'points' => $points,
                ]);
            }
        }
    }
}

// ============================================================================
// Driver Point Breakdown Tests
// ============================================================================

test('driver gets position points for classified race finish', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Test GP Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 1,
        'grid_position' => 3,
        'status' => 'classified',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('event_id', $event->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    expect($point)->not->toBeNull();
    expect((float) $point->points)->toBe(25.0);
    expect((float) $point->breakdown['position'])->toBe(25.0);
});

test('driver gets fastest lap bonus in race', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);
    seedBonusPoints($scenario['franchise']->id, [
        'race' => ['fastest_lap' => ['driver' => 5]],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Test GP Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 5,
        'grid_position' => 5,
        'status' => 'classified',
        'fastest_lap' => true,
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    expect((float) $point->points)->toBe(15.0); // 10 for P5 + 5 fastest lap
    expect((float) $point->breakdown['fastest_lap'])->toBe(5.0);
});

test('driver gets driver of the day bonus in race', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);
    seedBonusPoints($scenario['franchise']->id, [
        'race' => ['driver_of_the_day' => ['driver' => 3]],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Test GP Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 10,
        'grid_position' => 10,
        'status' => 'classified',
        'driver_of_the_day' => true,
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    expect((float) $point->points)->toBe(4.0); // 1 for P10 + 3 DOTD
    expect((float) $point->breakdown['driver_of_the_day'])->toBe(3.0);
});

test('driver gets DNF penalty when no result exists', function () {
    $scenario = createPointsScenario();
    seedBonusPoints($scenario['franchise']->id, [
        'race' => ['dnf_penalty' => ['driver' => -10]],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Test GP Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // No EventResult for driver 0 — they didn't participate/DNF

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    expect($point)->not->toBeNull();
    expect((float) $point->points)->toBe(-10.0);
    expect((float) $point->breakdown['dnf_penalty'])->toBe(-10.0);
    expect($point->breakdown['note'])->toBe('no_result');
});

test('driver gets DNF penalty for DNF status', function () {
    $scenario = createPointsScenario();
    seedBonusPoints($scenario['franchise']->id, [
        'race' => ['dnf_penalty' => ['driver' => -10]],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Test GP Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => null,
        'grid_position' => 5,
        'status' => 'dnf',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    expect((float) $point->points)->toBe(-10.0);
    expect((float) $point->breakdown['dnf_penalty'])->toBe(-10.0);
});

test('driver gets sprint positions gained and lost points', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);
    seedBonusPoints($scenario['franchise']->id, [
        'sprint' => [
            'positions_gained' => ['driver' => 2],
            'positions_lost' => ['driver' => -1],
        ],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Sprint',
        'type' => 'sprint',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // Driver gained 5 positions (grid 8 -> finish 3)
    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 3,
        'grid_position' => 8,
        'status' => 'classified',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    // P3 sprint = 6 pts + 5 positions gained × 2 = 10
    expect((float) $point->points)->toBe(16.0);
    expect((float) $point->breakdown['positions_gained'])->toBe(10.0);
});

test('driver gets sprint positions lost penalty', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);
    seedBonusPoints($scenario['franchise']->id, [
        'sprint' => [
            'positions_gained' => ['driver' => 2],
            'positions_lost' => ['driver' => -1],
        ],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Sprint',
        'type' => 'sprint',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // Driver lost 3 positions (grid 2 -> finish 5)
    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 5,
        'grid_position' => 2,
        'status' => 'classified',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    // P5 sprint = 4 pts + 3 positions lost × -1 = -3
    expect((float) $point->points)->toBe(1.0);
    expect((float) $point->breakdown['positions_lost'])->toBe(-3.0);
});

test('driver gets sprint overtake points', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);
    seedBonusPoints($scenario['franchise']->id, [
        'sprint' => ['overtake' => ['driver' => 1.5]],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Sprint',
        'type' => 'sprint',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 1,
        'grid_position' => 1,
        'status' => 'classified',
        'overtakes_made' => 4,
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    // P1 sprint = 8 + 4 overtakes × 1.5 = 6
    expect((float) $point->points)->toBe(14.0);
    expect((float) $point->breakdown['overtakes'])->toBe(6.0);
});

test('driver gets NC/DSQ penalty in qualifying', function () {
    $scenario = createPointsScenario();
    seedBonusPoints($scenario['franchise']->id, [
        'qualifying' => ['nc_dsq_penalty' => ['driver' => -5]],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Qualifying',
        'type' => 'qualifying',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => null,
        'grid_position' => null,
        'status' => 'dsq',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    expect((float) $point->points)->toBe(-5.0);
    expect((float) $point->breakdown['nc_dsq_penalty'])->toBe(-5.0);
});

// ============================================================================
// Constructor Point Breakdown Tests
// ============================================================================

test('constructor gets combined position points for both drivers in race', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // Driver 0 finishes P1, Driver 1 finishes P2 (constructor 0's drivers)
    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 1,
        'grid_position' => 1,
        'status' => 'classified',
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][1]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 2,
        'grid_position' => 2,
        'status' => 'classified',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'constructor')
        ->where('entity_id', $scenario['constructors'][0]->id)
        ->first();

    expect($point)->not->toBeNull();
    // P1 (25) + P2 (18) = 43
    expect((float) $point->points)->toBe(43.0);
    expect((float) $point->breakdown["position_{$scenario['drivers'][0]->id}"])->toBe(25.0);
    expect((float) $point->breakdown["position_{$scenario['drivers'][1]->id}"])->toBe(18.0);
});

test('constructor gets pitstop bracket points', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);
    seedBonusPoints($scenario['franchise']->id, [
        'race' => [
            'pitstop_under_2s' => ['constructor' => 10],
            'pitstop_2s_2.19s' => ['constructor' => 7],
            'pitstop_2.2s_2.49s' => ['constructor' => 5],
            'pitstop_2.5s_2.99s' => ['constructor' => 3],
        ],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // Constructor has a 2.1s pitstop
    EventPitstop::create([
        'event_id' => $event->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'stop_lap' => 20,
        'stop_time_seconds' => 2.100,
        'is_fastest_of_event' => false,
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'constructor')
        ->where('entity_id', $scenario['constructors'][0]->id)
        ->first();

    // No driver results so no position points, just pitstop bracket 2.0-2.19s = 7
    expect((float) $point->breakdown['pitstop_2s_2.19s'])->toBe(7.0);
});

test('constructor gets fastest pitstop bonus', function () {
    $scenario = createPointsScenario();
    seedBonusPoints($scenario['franchise']->id, [
        'race' => [
            'pitstop_under_2s' => ['constructor' => 10],
            'pitstop_fastest' => ['constructor' => 5],
        ],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventPitstop::create([
        'event_id' => $event->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'stop_lap' => 25,
        'stop_time_seconds' => 1.850,
        'is_fastest_of_event' => true,
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'constructor')
        ->where('entity_id', $scenario['constructors'][0]->id)
        ->first();

    // Under 2s = 10 + fastest = 5
    expect((float) $point->points)->toBe(15.0);
    expect((float) $point->breakdown['pitstop_under_2s'])->toBe(10.0);
    expect((float) $point->breakdown['pitstop_fastest'])->toBe(5.0);
});

test('constructor gets qualifying Q-stage bonuses for both drivers reaching Q3', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);
    seedBonusPoints($scenario['franchise']->id, [
        'qualifying' => [
            'constructor_both_reaches_q3' => ['constructor' => 10],
            'constructor_one_reaches_q3' => ['constructor' => 5],
            'constructor_both_reaches_q2' => ['constructor' => 3],
            'constructor_one_reaches_q2' => ['constructor' => 1],
            'constructor_neither_reaches_q2' => ['constructor' => -5],
        ],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Qualifying',
        'type' => 'qualifying',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 1,
        'status' => 'classified',
        'q1_time' => '00:01:20',
        'q2_time' => '00:01:19',
        'q3_time' => '00:01:18',
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][1]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 3,
        'status' => 'classified',
        'q1_time' => '00:01:21',
        'q2_time' => '00:01:20',
        'q3_time' => '00:01:19',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'constructor')
        ->where('entity_id', $scenario['constructors'][0]->id)
        ->first();

    // P1 (10) + P3 (6) + both Q3 bonus (10) = 26
    expect((float) $point->points)->toBe(26.0);
    expect((float) $point->breakdown['constructor_both_reaches_q3'])->toBe(10.0);
});

test('constructor gets qualifying bonus for one driver reaching Q3', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);
    seedBonusPoints($scenario['franchise']->id, [
        'qualifying' => [
            'constructor_both_reaches_q3' => ['constructor' => 10],
            'constructor_one_reaches_q3' => ['constructor' => 5],
            'constructor_both_reaches_q2' => ['constructor' => 3],
            'constructor_one_reaches_q2' => ['constructor' => 1],
            'constructor_neither_reaches_q2' => ['constructor' => -5],
        ],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Qualifying',
        'type' => 'qualifying',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // Driver 0 reaches Q3, Driver 1 only reaches Q2
    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 5,
        'status' => 'classified',
        'q1_time' => '00:01:20',
        'q2_time' => '00:01:19',
        'q3_time' => '00:01:18',
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][1]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 12,
        'status' => 'classified',
        'q1_time' => '00:01:21',
        'q2_time' => '00:01:20',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'constructor')
        ->where('entity_id', $scenario['constructors'][0]->id)
        ->first();

    expect((float) $point->breakdown['constructor_one_reaches_q3'])->toBe(5.0);
});

test('constructor gets neither reaches Q2 penalty', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);
    seedBonusPoints($scenario['franchise']->id, [
        'qualifying' => [
            'constructor_neither_reaches_q2' => ['constructor' => -5],
        ],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Qualifying',
        'type' => 'qualifying',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // Both drivers only have Q1 times
    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 18,
        'status' => 'classified',
        'q1_time' => '00:01:25',
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][1]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 19,
        'status' => 'classified',
        'q1_time' => '00:01:26',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'constructor')
        ->where('entity_id', $scenario['constructors'][0]->id)
        ->first();

    expect((float) $point->breakdown['constructor_neither_reaches_q2'])->toBe(-5.0);
});

test('constructor gets DSQ penalty in race', function () {
    $scenario = createPointsScenario();
    seedBonusPoints($scenario['franchise']->id, [
        'race' => [
            'constructor_dsq' => ['constructor' => -15],
            'dnf_penalty' => ['driver' => -10],
        ],
    ]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => null,
        'grid_position' => 5,
        'status' => 'dsq',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    $point = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'constructor')
        ->where('entity_id', $scenario['constructors'][0]->id)
        ->first();

    // DSQ penalty (-15) + driver's penalty is counted against constructor too (-10)
    expect((float) $point->breakdown['constructor_dsq'])->toBe(-15.0);
    expect((float) $point->breakdown["dnf_{$scenario['drivers'][0]->id}"])->toBe(-10.0);
});

// ============================================================================
// Roster Snapshot vs Live Roster Tests
// ============================================================================

test('uses roster snapshot when available instead of live roster', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // Snapshot has driver 2 in seat (not driver 1), driver 0 in seat
    RosterSnapshot::create([
        'event_id' => $event->id,
        'fantasy_team_id' => $scenario['team']->id,
        'snapshot' => [
            ['entity_type' => 'constructor', 'entity_id' => $scenario['constructors'][0]->id, 'in_seat' => true],
            ['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id, 'in_seat' => true],
            ['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id, 'in_seat' => true],
            ['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][1]->id, 'in_seat' => false],
        ],
    ]);

    // Create results for driver 2 (who was in seat at snapshot)
    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][2]->id,
        'constructor_id' => $scenario['constructors'][1]->id,
        'finish_position' => 1,
        'grid_position' => 1,
        'status' => 'classified',
    ]);

    // Driver 0 result
    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 5,
        'grid_position' => 5,
        'status' => 'classified',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    // Driver 2 should have points (was in seat in snapshot)
    $driver2Points = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][2]->id)
        ->first();

    expect($driver2Points)->not->toBeNull();
    expect((float) $driver2Points->points)->toBe(25.0);

    // Driver 1 should NOT have points (was on bench in snapshot)
    $driver1Points = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][1]->id)
        ->first();

    expect($driver1Points)->toBeNull();
});

test('falls back to live roster when no snapshot exists', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // No snapshot created — live roster has drivers 0 & 1 in seat, driver 2 on bench

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 1,
        'grid_position' => 1,
        'status' => 'classified',
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][1]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 3,
        'grid_position' => 3,
        'status' => 'classified',
    ]);

    // Driver 2 (bench) also has result but should not be scored
    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][2]->id,
        'constructor_id' => $scenario['constructors'][1]->id,
        'finish_position' => 2,
        'grid_position' => 2,
        'status' => 'classified',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForTeam($scenario['team'], $event);

    // In-seat drivers should have points
    $driver0Points = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->first();

    expect($driver0Points)->not->toBeNull();
    expect((float) $driver0Points->points)->toBe(25.0);

    // Bench driver should NOT have points
    $driver2Points = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][2]->id)
        ->first();

    expect($driver2Points)->toBeNull();
});

test('calculateForEvent processes all teams across all leagues for the season', function () {
    $scenario = createPointsScenario();
    seedPositionPoints($scenario['franchise']->id);

    // Create a second team in the same league
    $user2 = User::factory()->create();
    LeagueMember::create(['league_id' => $scenario['league']->id, 'user_id' => $user2->id, 'role' => 'member', 'joined_at' => now()]);
    $team2 = FantasyTeam::factory()->create(['league_id' => $scenario['league']->id, 'user_id' => $user2->id]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team2->id, 'entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team2->id, 'entity_type' => 'constructor', 'entity_id' => $scenario['constructors'][1]->id, 'in_seat' => true, 'acquired_at' => now()]);

    $event = Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][0]->id,
        'constructor_id' => $scenario['constructors'][0]->id,
        'finish_position' => 1,
        'status' => 'classified',
    ]);

    EventResult::create([
        'event_id' => $event->id,
        'driver_id' => $scenario['drivers'][2]->id,
        'constructor_id' => $scenario['constructors'][1]->id,
        'finish_position' => 3,
        'status' => 'classified',
    ]);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForEvent($event);

    // Both teams should have fantasy event points
    $team1Points = FantasyEventPoint::where('fantasy_team_id', $scenario['team']->id)->count();
    $team2Points = FantasyEventPoint::where('fantasy_team_id', $team2->id)->count();

    expect($team1Points)->toBeGreaterThan(0);
    expect($team2Points)->toBeGreaterThan(0);
});
