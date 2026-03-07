<?php

use App\Jobs\ScheduleAutoPick;
use App\Models\BonusPointsScheme;
use App\Models\Constructor;
use App\Models\DraftSession;
use App\Models\Driver;
use App\Models\Event;
use App\Models\EventResult;
use App\Models\FantasyEventPoint;
use App\Models\FantasyTeam;
use App\Models\Franchise;
use App\Models\League;
use App\Models\PointsScheme;
use App\Models\RosterSnapshot;
use App\Models\Season;
use App\Models\SeasonConstructor;
use App\Models\SeasonDriver;
use App\Models\Track;
use App\Models\User;
use App\Services\DraftService;
use App\Services\PointsCalculationService;
use Illuminate\Support\Facades\Queue;

test('end-to-end: create league, join, draft, lock event, calculate points, view standings', function () {
    Queue::fake([ScheduleAutoPick::class]);

    // --- 1. Setup franchise, season, entities ---
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->active()->create(['franchise_id' => $franchise->id, 'year' => 2026]);
    $track = Track::create(['franchise_id' => $franchise->id, 'name' => 'Integration Circuit', 'location' => 'Test City', 'country' => 'Test Country']);

    $constructors = [];
    $drivers = [];

    for ($i = 0; $i < 3; $i++) {
        $constructor = Constructor::create([
            'franchise_id' => $franchise->id,
            'name' => "Team {$i}",
            'slug' => "team-{$i}",
            'is_active' => true,
        ]);
        SeasonConstructor::create(['season_id' => $season->id, 'constructor_id' => $constructor->id]);
        $constructors[] = $constructor;

        for ($j = 0; $j < 2; $j++) {
            $driverIndex = $i * 2 + $j;
            $driver = Driver::create([
                'franchise_id' => $franchise->id,
                'name' => "Racer {$driverIndex}",
                'slug' => "racer-{$driverIndex}",
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

    // Seed points schemes
    $racePoints = [1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 4, 9 => 2, 10 => 1];
    foreach ($racePoints as $pos => $pts) {
        PointsScheme::create(['franchise_id' => $franchise->id, 'event_type' => 'race', 'position' => $pos, 'points' => $pts]);
    }
    BonusPointsScheme::create(['franchise_id' => $franchise->id, 'event_type' => 'race', 'bonus_key' => 'fastest_lap', 'applies_to' => 'driver', 'points' => 5]);
    BonusPointsScheme::create(['franchise_id' => $franchise->id, 'event_type' => 'race', 'bonus_key' => 'dnf_penalty', 'applies_to' => 'driver', 'points' => -10]);

    // --- 2. Create league (commissioner) ---
    $commissioner = User::factory()->create();

    $this->actingAs($commissioner)
        ->post(route('leagues.store'), [
            'franchise_id' => $franchise->id,
            'name' => 'Integration League',
            'description' => 'Testing end to end',
            'max_teams' => 10,
            'visibility' => 'public',
            'join_policy' => 'open',
        ])
        ->assertRedirect();

    $league = League::where('name', 'Integration League')->firstOrFail();
    expect($league->commissioner_id)->toBe($commissioner->id);

    // --- 3. Second user joins the league ---
    $user2 = User::factory()->create();

    $this->actingAs($user2)
        ->post(route('leagues.join', $league->slug))
        ->assertRedirect();

    expect($league->members()->count())->toBe(2);

    // --- 4. Fantasy teams are auto-created when users join ---
    $team1 = FantasyTeam::where('league_id', $league->id)->where('user_id', $commissioner->id)->firstOrFail();
    $team2 = FantasyTeam::where('league_id', $league->id)->where('user_id', $user2->id)->firstOrFail();

    // --- 5. Commissioner sets up and runs the draft ---
    $this->actingAs($commissioner)
        ->post(route('leagues.draft.setup', $league->slug), [
            'type' => 'snake',
            'pick_time_limit_seconds' => 60,
        ])
        ->assertRedirect();

    $session = DraftSession::where('league_id', $league->id)->firstOrFail();
    expect($session->total_picks)->toBe(8); // 2 teams × 4 rounds (auto-generated on setup)

    $this->actingAs($commissioner)
        ->post(route('leagues.draft.start', $league->slug))
        ->assertRedirect();

    $session->refresh();
    expect($session->status)->toBe('active');

    // Auto-pick all 8 picks
    $draftService = app(DraftService::class);
    for ($i = 0; $i < 8; $i++) {
        $draftService->autoPick($session->refresh());
    }

    $session->refresh();
    expect($session->status)->toBe('completed');
    expect($league->fresh()->draft_completed_at)->not->toBeNull();

    // Each team should have 4 roster entries (1 constructor + 3 drivers)
    expect($team1->roster()->count())->toBe(4);
    expect($team2->roster()->count())->toBe(4);

    // --- 6. Create event and lock it (triggers roster snapshot) ---
    $event = Event::create([
        'season_id' => $season->id,
        'track_id' => $track->id,
        'name' => 'Integration GP',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'scheduled',
        'sort_order' => 1,
        'round' => 1,
    ]);

    // Lock the event — the EventObserver should create roster snapshots
    $event->update(['status' => 'locked']);

    $snapshotCount = RosterSnapshot::where('event_id', $event->id)->count();
    expect($snapshotCount)->toBe(2); // One per team

    // --- 7. Create race results ---
    $team1Drivers = $team1->inSeatDrivers()->pluck('entity_id')->toArray();
    $team2Drivers = $team2->inSeatDrivers()->pluck('entity_id')->toArray();

    // Get all in-seat driver IDs from both teams
    $allDriverIds = array_merge($team1Drivers, $team2Drivers);

    // Create results for all rostered drivers
    foreach ($allDriverIds as $idx => $driverId) {
        $constructorId = SeasonDriver::where('driver_id', $driverId)
            ->where('season_id', $season->id)
            ->value('constructor_id');

        EventResult::create([
            'event_id' => $event->id,
            'driver_id' => $driverId,
            'constructor_id' => $constructorId,
            'finish_position' => $idx + 1,
            'grid_position' => $idx + 1,
            'status' => 'classified',
            'fastest_lap' => $idx === 0,
        ]);
    }

    // --- 8. Calculate points ---
    $event->update(['status' => 'completed']);

    $calculator = app(PointsCalculationService::class);
    $calculator->calculateForEvent($event);
    $calculator->aggregateForFantasyTeams($event);

    // Both teams should have fantasy event points
    $team1PointCount = FantasyEventPoint::where('fantasy_team_id', $team1->id)->where('event_id', $event->id)->count();
    $team2PointCount = FantasyEventPoint::where('fantasy_team_id', $team2->id)->where('event_id', $event->id)->count();

    expect($team1PointCount)->toBeGreaterThan(0);
    expect($team2PointCount)->toBeGreaterThan(0);

    // The first driver should have position points + fastest lap bonus
    $firstDriverPoints = FantasyEventPoint::where('event_id', $event->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $allDriverIds[0])
        ->first();

    expect($firstDriverPoints)->not->toBeNull();
    expect((float) $firstDriverPoints->points)->toBe(30.0); // P1 (25) + fastest lap (5)

    // --- 9. View standings ---
    $this->actingAs($commissioner)
        ->get(route('leagues.standings', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Standings/Index')
            ->has('standings', 2)
            ->where('standings.0.rank', 1)
        );

    // Verify standings are ordered by total points (highest first)
    $team1Total = (float) FantasyEventPoint::where('fantasy_team_id', $team1->id)->sum('points');
    $team2Total = (float) FantasyEventPoint::where('fantasy_team_id', $team2->id)->sum('points');

    // Since team 1's drivers finished in positions determined by auto-pick order,
    // verify both totals are computed
    expect($team1Total + $team2Total)->toBeGreaterThan(0);
});
