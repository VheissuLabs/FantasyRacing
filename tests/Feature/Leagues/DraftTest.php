<?php

use App\Jobs\ScheduleAutoPick;
use App\Jobs\SendDraftStartingNotifications;
use App\Models\Constructor;
use App\Models\DraftSession;
use App\Models\Driver;
use App\Models\FantasyTeam;
use App\Models\Franchise;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Season;
use App\Models\SeasonConstructor;
use App\Models\SeasonDriver;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * Create a league with teams and season entities for draft testing.
 *
 * @return array{league: League, commissioner: User, users: User[], teams: FantasyTeam[], constructors: Constructor[], drivers: Driver[]}
 */
function createDraftScenario(int $teamCount = 2): array
{
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
    ]);

    LeagueMember::create(['league_id' => $league->id, 'user_id' => $commissioner->id, 'role' => 'commissioner', 'joined_at' => now()]);

    $teams = [FantasyTeam::factory()->create(['league_id' => $league->id, 'user_id' => $commissioner->id])];
    $users = [$commissioner];

    for ($i = 1; $i < $teamCount; $i++) {
        $user = User::factory()->create();
        LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);
        $teams[] = FantasyTeam::factory()->create(['league_id' => $league->id, 'user_id' => $user->id]);
        $users[] = $user;
    }

    // Create season entities
    $constructors = [];
    $drivers = [];

    for ($i = 0; $i < 3; $i++) {
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
                'effective_from' => "{$season->year}-01-01",
            ]);
            $drivers[] = $driver;
        }
    }

    return compact('league', 'commissioner', 'users', 'teams', 'constructors', 'drivers');
}

/**
 * Create a draft session with generated order, ready to start or already started.
 */
function createReadyDraft(array $scenario, bool $start = false): DraftSession
{
    $session = DraftSession::create([
        'league_id' => $scenario['league']->id,
        'type' => 'snake',
        'pick_time_limit_seconds' => 60,
        'status' => 'pending',
        'total_picks' => 0,
    ]);

    $draftService = app(\App\Services\DraftService::class);
    $draftService->randomizeOrder($session);

    if ($start) {
        // Fake queue so ScheduleAutoPick doesn't fire synchronously
        Queue::fake([ScheduleAutoPick::class]);
        $draftService->start($session);
    }

    return $session;
}

// --- Draft page access ---

test('guests cannot access the draft page', function () {
    $league = League::factory()->create();

    $this->get(route('leagues.draft', $league->slug))
        ->assertRedirect(route('login'));
});

test('authenticated users can access the draft page with a session', function () {
    $scenario = createDraftScenario();
    DraftSession::create([
        'league_id' => $scenario['league']->id,
        'type' => 'snake',
        'pick_time_limit_seconds' => 60,
        'status' => 'pending',
        'total_picks' => 0,
    ]);

    $this->actingAs($scenario['commissioner'])
        ->get(route('leagues.draft', $scenario['league']->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Draft/Show')
            ->has('session')
            ->where('isCommissioner', true)
        );
});

test('draft page works when no session exists', function () {
    $scenario = createDraftScenario();

    $this->actingAs($scenario['commissioner'])
        ->get(route('leagues.draft', $scenario['league']->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Draft/Show')
            ->where('session', null)
            ->where('isCommissioner', true)
            ->where('teamCount', 2)
        );
});

test('draft page passes teams, allDrivers, and allConstructors props', function () {
    $scenario = createDraftScenario();

    $this->actingAs($scenario['commissioner'])
        ->get(route('leagues.draft', $scenario['league']->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Draft/Show')
            ->has('teams', 2)
            ->has('allDrivers', 6)
            ->has('allConstructors', 3)
        );
});

// --- Draft setup ---

test('commissioner can create a draft session', function () {
    $scenario = createDraftScenario();

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.setup', $scenario['league']->slug), [
            'type' => 'snake',
            'pick_time_limit_seconds' => 90,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('draft_sessions', [
        'league_id' => $scenario['league']->id,
        'type' => 'snake',
        'pick_time_limit_seconds' => 90,
        'status' => 'pending',
    ]);
});

test('non-commissioner cannot create a draft session', function () {
    $scenario = createDraftScenario();

    $this->actingAs($scenario['users'][1])
        ->post(route('leagues.draft.setup', $scenario['league']->slug), [
            'type' => 'snake',
            'pick_time_limit_seconds' => 60,
        ])
        ->assertForbidden();
});

test('cannot create a second draft session', function () {
    $scenario = createDraftScenario();
    DraftSession::create([
        'league_id' => $scenario['league']->id,
        'type' => 'snake',
        'pick_time_limit_seconds' => 60,
        'status' => 'pending',
        'total_picks' => 0,
    ]);

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.setup', $scenario['league']->slug), [
            'type' => 'snake',
            'pick_time_limit_seconds' => 60,
        ])
        ->assertStatus(409);
});

// --- Setup generates order ---

test('draft setup automatically generates a randomized order', function () {
    $scenario = createDraftScenario();

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.setup', $scenario['league']->slug), [
            'type' => 'snake',
            'pick_time_limit_seconds' => 60,
        ])
        ->assertRedirect();

    $session = $scenario['league']->draftSession;
    expect($session->total_picks)->toBe(8); // 2 teams × 4 rounds
    expect($session->orders()->count())->toBe(8);
});

// --- Start draft ---

test('commissioner can start a draft after generating order', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    $session = createReadyDraft($scenario);

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.start', $scenario['league']->slug))
        ->assertRedirect();

    $session->refresh();
    expect($session->status)->toBe('active');
    expect($session->started_at)->not->toBeNull();
});

test('cannot start draft without generating order first', function () {
    $scenario = createDraftScenario();
    DraftSession::create([
        'league_id' => $scenario['league']->id,
        'type' => 'snake',
        'pick_time_limit_seconds' => 60,
        'status' => 'pending',
        'total_picks' => 0,
    ]);

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.start', $scenario['league']->slug))
        ->assertRedirect()
        ->assertSessionHasErrors('draft');
});

// --- Pause / Resume ---

test('commissioner can pause and resume a draft', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    $session = createReadyDraft($scenario, start: true);

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.pause', $scenario['league']->slug))
        ->assertRedirect();

    $session->refresh();
    expect($session->status)->toBe('paused');

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.resume', $scenario['league']->slug))
        ->assertRedirect();

    $session->refresh();
    expect($session->status)->toBe('active');
});

// --- Making picks ---

test('team can make a pick on their turn', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    $session = createReadyDraft($scenario, start: true);

    // First pick is round 1 (constructor round) for team 1
    $firstOrder = $session->orders()->where('pick_number', 1)->first();
    $pickingUser = collect($scenario['teams'])->first(fn ($team) => $team->id === $firstOrder->fantasy_team_id)->user;

    $this->actingAs($pickingUser ?? $scenario['commissioner'])
        ->post(route('leagues.draft.pick', $scenario['league']->slug), [
            'entity_type' => 'constructor',
            'entity_id' => $scenario['constructors'][0]->id,
        ])
        ->assertRedirect();

    $session->refresh();
    expect($session->current_pick_number)->toBe(2);
    expect($session->picks()->count())->toBe(1);
});

test('wrong team cannot pick', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    $session = createReadyDraft($scenario, start: true);

    // Figure out which user is NOT first
    $firstOrder = $session->orders()->where('pick_number', 1)->first();
    $wrongUser = collect($scenario['users'])->first(function ($user) use ($scenario, $firstOrder) {
        $userTeam = collect($scenario['teams'])->first(fn ($team) => $team->user_id === $user->id);

        return $userTeam && $userTeam->id !== $firstOrder->fantasy_team_id;
    });

    $this->actingAs($wrongUser)
        ->post(route('leagues.draft.pick', $scenario['league']->slug), [
            'entity_type' => 'constructor',
            'entity_id' => $scenario['constructors'][0]->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('draft');
});

// --- Full draft completion ---

test('completing all picks creates rosters and marks draft complete', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    $session = createReadyDraft($scenario, start: true);

    $draftService = app(\App\Services\DraftService::class);

    // Auto-pick all 8 slots
    for ($i = 0; $i < 8; $i++) {
        $draftService->autoPick($session->refresh());
    }

    $session->refresh();
    expect($session->status)->toBe('completed');
    expect($session->completed_at)->not->toBeNull();

    // Both teams should have rosters
    foreach ($scenario['teams'] as $team) {
        $rosterCount = $team->roster()->count();
        expect($rosterCount)->toBe(4); // 1 constructor + 3 drivers
    }

    // Free agent pool should be seeded
    $scenario['league']->refresh();
    expect($scenario['league']->draft_completed_at)->not->toBeNull();
});

// --- Update order ---

test('commissioner can set custom team order', function () {
    $scenario = createDraftScenario();
    createReadyDraft($scenario);

    $reversedTeamIds = array_reverse(array_map(fn ($team) => $team->id, $scenario['teams']));

    $this->actingAs($scenario['commissioner'])
        ->put(route('leagues.draft.update-order', $scenario['league']->slug), [
            'team_ids' => $reversedTeamIds,
        ])
        ->assertRedirect();

    $session = $scenario['league']->draftSession->fresh();
    $firstRoundOrder = $session->orders()->where('round', 1)->orderBy('pick_number')->pluck('fantasy_team_id')->toArray();

    expect($firstRoundOrder)->toBe($reversedTeamIds);
});

test('cannot update order with mismatched team ids', function () {
    $scenario = createDraftScenario();
    createReadyDraft($scenario);

    $this->actingAs($scenario['commissioner'])
        ->put(route('leagues.draft.update-order', $scenario['league']->slug), [
            'team_ids' => [999, 998],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('draft');
});

test('non-commissioner cannot update draft order', function () {
    $scenario = createDraftScenario();
    createReadyDraft($scenario);

    $teamIds = array_map(fn ($team) => $team->id, $scenario['teams']);

    $this->actingAs($scenario['users'][1])
        ->put(route('leagues.draft.update-order', $scenario['league']->slug), [
            'team_ids' => $teamIds,
        ])
        ->assertForbidden();
});

test('cannot update order for active draft', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    createReadyDraft($scenario, start: true);

    $teamIds = array_map(fn ($team) => $team->id, $scenario['teams']);

    $this->actingAs($scenario['commissioner'])
        ->put(route('leagues.draft.update-order', $scenario['league']->slug), [
            'team_ids' => $teamIds,
        ])
        ->assertStatus(422);
});

// --- Draft scheduling ---

test('commissioner can set scheduled_at during setup', function () {
    Queue::fake([SendDraftStartingNotifications::class]);

    $scenario = createDraftScenario();
    $scheduledAt = now()->addDay()->format('Y-m-d\TH:i');

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.setup', $scenario['league']->slug), [
            'type' => 'snake',
            'pick_time_limit_seconds' => 60,
            'scheduled_at' => $scheduledAt,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('draft_sessions', [
        'league_id' => $scenario['league']->id,
        'status' => 'pending',
    ]);

    $session = $scenario['league']->draftSession;
    expect($session->scheduled_at)->not->toBeNull();

    Queue::assertPushed(SendDraftStartingNotifications::class);
});

test('setup without scheduled_at does not dispatch notifications', function () {
    Queue::fake([SendDraftStartingNotifications::class]);

    $scenario = createDraftScenario();

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.setup', $scenario['league']->slug), [
            'type' => 'snake',
            'pick_time_limit_seconds' => 60,
        ])
        ->assertRedirect();

    Queue::assertNotPushed(SendDraftStartingNotifications::class);
});

test('commissioner can schedule a draft date on a pending session', function () {
    Queue::fake([SendDraftStartingNotifications::class]);

    $scenario = createDraftScenario();
    DraftSession::create([
        'league_id' => $scenario['league']->id,
        'type' => 'snake',
        'pick_time_limit_seconds' => 60,
        'status' => 'pending',
        'total_picks' => 0,
    ]);

    $scheduledAt = now()->addDays(2)->format('Y-m-d\TH:i');

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.schedule', $scenario['league']->slug), [
            'scheduled_at' => $scheduledAt,
        ])
        ->assertRedirect();

    $session = $scenario['league']->draftSession->fresh();
    expect($session->scheduled_at)->not->toBeNull();
    expect($session->notified_at)->toBeNull();

    Queue::assertPushed(SendDraftStartingNotifications::class);
});

test('non-commissioner cannot schedule a draft', function () {
    $scenario = createDraftScenario();
    DraftSession::create([
        'league_id' => $scenario['league']->id,
        'type' => 'snake',
        'pick_time_limit_seconds' => 60,
        'status' => 'pending',
        'total_picks' => 0,
    ]);

    $this->actingAs($scenario['users'][1])
        ->post(route('leagues.draft.schedule', $scenario['league']->slug), [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertForbidden();
});

test('cannot schedule on active or completed sessions', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    createReadyDraft($scenario, start: true);

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.schedule', $scenario['league']->slug), [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertStatus(422);
});

// --- Restart ---

test('commissioner can restart an active draft', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    $session = createReadyDraft($scenario, start: true);

    // Make a pick first
    $draftService = app(\App\Services\DraftService::class);
    $draftService->autoPick($session->refresh());

    expect($session->refresh()->picks()->count())->toBe(1);

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.restart', $scenario['league']->slug))
        ->assertRedirect()
        ->assertSessionHas('success');

    $session->refresh();
    expect($session->status)->toBe('pending');
    expect($session->current_pick_number)->toBe(1);
    expect($session->started_at)->toBeNull();
    expect($session->paused_by)->toBeNull();
    expect($session->picks()->count())->toBe(0);
    expect($session->orders()->where('status', '!=', 'pending')->count())->toBe(0);
});

test('commissioner can restart a completed draft', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    $session = createReadyDraft($scenario, start: true);

    $draftService = app(\App\Services\DraftService::class);
    for ($i = 0; $i < 8; $i++) {
        $draftService->autoPick($session->refresh());
    }

    $session->refresh();
    expect($session->status)->toBe('completed');
    expect($scenario['league']->fresh()->draft_completed_at)->not->toBeNull();

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.restart', $scenario['league']->slug))
        ->assertRedirect()
        ->assertSessionHas('success');

    $session->refresh();
    expect($session->status)->toBe('pending');
    expect($session->picks()->count())->toBe(0);
    expect($scenario['league']->fresh()->draft_completed_at)->toBeNull();
    expect(\App\Models\FantasyTeamRoster::whereIn('fantasy_team_id', collect($scenario['teams'])->pluck('id'))->count())->toBe(0);
    expect(\App\Models\FreeAgentPool::where('league_id', $scenario['league']->id)->count())->toBe(0);
});

test('non-commissioner cannot restart a draft', function () {
    Queue::fake([ScheduleAutoPick::class]);

    $scenario = createDraftScenario();
    createReadyDraft($scenario, start: true);

    $this->actingAs($scenario['users'][1])
        ->post(route('leagues.draft.restart', $scenario['league']->slug))
        ->assertForbidden();
});

test('cannot restart a pending draft', function () {
    $scenario = createDraftScenario();
    createReadyDraft($scenario);

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.restart', $scenario['league']->slug))
        ->assertStatus(422);
});

test('scheduled date must be in the future', function () {
    $scenario = createDraftScenario();
    DraftSession::create([
        'league_id' => $scenario['league']->id,
        'type' => 'snake',
        'pick_time_limit_seconds' => 60,
        'status' => 'pending',
        'total_picks' => 0,
    ]);

    $this->actingAs($scenario['commissioner'])
        ->post(route('leagues.draft.schedule', $scenario['league']->slug), [
            'scheduled_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('scheduled_at');
});
