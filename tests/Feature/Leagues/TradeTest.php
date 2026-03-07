<?php

use App\Models\Constructor;
use App\Models\Driver;
use App\Models\Event;
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
use App\Models\Trade;
use App\Models\User;
use App\Notifications\TradeReceivedNotification;
use App\Notifications\TradeResolvedNotification;
use App\Services\TradeService;
use Illuminate\Support\Facades\Notification;

/**
 * Create a full trade scenario with two teams, each with roster entries.
 *
 * @return array{league: League, franchise: Franchise, season: Season, commissioner: User, user1: User, user2: User, team1: FantasyTeam, team2: FantasyTeam, drivers: Driver[], constructors: Constructor[]}
 */
function createTradeScenario(array $rules = []): array
{
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id, 'year' => 2026]);
    $track = Track::create(['franchise_id' => $franchise->id, 'name' => 'Trade Circuit', 'location' => 'Trade City', 'country' => 'Test Country']);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'rules' => array_merge(['trade_approval_required' => true, 'no_duplicates' => true], $rules),
    ]);

    LeagueMember::create(['league_id' => $league->id, 'user_id' => $commissioner->id, 'role' => 'commissioner', 'joined_at' => now()]);

    $user2 = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user2->id, 'role' => 'member', 'joined_at' => now()]);

    $team1 = FantasyTeam::where('league_id', $league->id)->where('user_id', $commissioner->id)->first();
    $team2 = FantasyTeam::where('league_id', $league->id)->where('user_id', $user2->id)->first();

    $constructors = [];
    $drivers = [];

    for ($i = 0; $i < 2; $i++) {
        $constructor = Constructor::create([
            'franchise_id' => $franchise->id,
            'name' => "Trade Constructor {$i}",
            'slug' => "trade-constructor-{$i}-" . uniqid(),
            'is_active' => true,
        ]);
        SeasonConstructor::create(['season_id' => $season->id, 'constructor_id' => $constructor->id]);
        $constructors[] = $constructor;

        for ($j = 0; $j < 2; $j++) {
            $driverIndex = $i * 2 + $j;
            $driver = Driver::create([
                'franchise_id' => $franchise->id,
                'name' => "Trade Driver {$driverIndex}",
                'slug' => "trade-driver-{$driverIndex}-" . uniqid(),
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

    // Team 1 roster: constructor 0, drivers 0 & 1
    FantasyTeamRoster::create(['fantasy_team_id' => $team1->id, 'entity_type' => 'constructor', 'entity_id' => $constructors[0]->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team1->id, 'entity_type' => 'driver', 'entity_id' => $drivers[0]->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team1->id, 'entity_type' => 'driver', 'entity_id' => $drivers[1]->id, 'in_seat' => true, 'acquired_at' => now()]);

    // Team 2 roster: constructor 1, drivers 2 & 3
    FantasyTeamRoster::create(['fantasy_team_id' => $team2->id, 'entity_type' => 'constructor', 'entity_id' => $constructors[1]->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team2->id, 'entity_type' => 'driver', 'entity_id' => $drivers[2]->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $team2->id, 'entity_type' => 'driver', 'entity_id' => $drivers[3]->id, 'in_seat' => true, 'acquired_at' => now()]);

    return ['league' => $league, 'franchise' => $franchise, 'season' => $season, 'commissioner' => $commissioner, 'team1' => $team1, 'team2' => $team2, 'drivers' => $drivers, 'constructors' => $constructors, 'track' => $track, 'user1' => $commissioner, 'user2' => $user2];
}

// ============================================================================
// Trade Proposal Creation & Validation
// ============================================================================

test('trade proposal creates pending trade with trade items', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    expect($trade->status)->toBe('pending');
    expect($trade->initiator_team_id)->toBe($scenario['team1']->id);
    expect($trade->receiver_team_id)->toBe($scenario['team2']->id);
    expect($trade->items()->count())->toBe(2);

    Notification::assertSentTo($scenario['user2'], TradeReceivedNotification::class);
});

test('trade without approval requirement executes immediately', function () {
    Notification::fake();

    $scenario = createTradeScenario(['trade_approval_required' => false]);
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    expect($trade->status)->toBe('completed');
    expect($trade->resolved_at)->not->toBeNull();

    // Roster should have been swapped
    $team1HasDriver2 = FantasyTeamRoster::where('fantasy_team_id', $scenario['team1']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][2]->id)
        ->exists();

    $team2HasDriver0 = FantasyTeamRoster::where('fantasy_team_id', $scenario['team2']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)
        ->exists();

    expect($team1HasDriver2)->toBeTrue();
    expect($team2HasDriver0)->toBeTrue();
});

test('trade proposal via controller requires authentication', function () {
    $scenario = createTradeScenario();

    $this->post(route('leagues.trades.store', $scenario['league']->slug), [
        'receiver_team_id' => $scenario['team2']->id,
        'giving' => [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        'receiving' => [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    ])->assertRedirect(route('login'));
});

test('trade proposal validates required fields', function () {
    $scenario = createTradeScenario();

    $this->actingAs($scenario['user1'])
        ->post(route('leagues.trades.store', $scenario['league']->slug), [])
        ->assertSessionHasErrors(['giving', 'receiving']);
});

test('trade proposal validates entity types', function () {
    $scenario = createTradeScenario();

    $this->actingAs($scenario['user1'])
        ->post(route('leagues.trades.store', $scenario['league']->slug), [
            'receiver_team_id' => $scenario['team2']->id,
            'giving' => [['entity_type' => 'invalid', 'entity_id' => 1]],
            'receiving' => [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
        ])
        ->assertSessionHasErrors('giving.0.entity_type');
});

// ============================================================================
// Lock Enforcement
// ============================================================================

test('trade is blocked when entities are in an active locked event window', function () {
    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    // Create a locked event
    Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Locked Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'locked',
        'sort_order' => 1,
        'round' => 1,
    ]);

    expect(fn () => $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    ))->toThrow(InvalidArgumentException::class, 'locked event');
});

test('trade is allowed when no events are locked', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    // Create a completed event (not locked)
    Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Completed Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'completed',
        'sort_order' => 1,
        'round' => 1,
    ]);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    expect($trade)->toBeInstanceOf(Trade::class);
    expect($trade->status)->toBe('pending');
});

test('lock enforcement also applies when accepting a trade', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    // Create trade while no events are locked
    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    // Now lock an event
    Event::create([
        'season_id' => $scenario['season']->id,
        'track_id' => $scenario['track']->id,
        'name' => 'Locked Race',
        'type' => 'race',
        'scheduled_at' => now()->subDay(),
        'status' => 'locked',
        'sort_order' => 1,
        'round' => 1,
    ]);

    expect(fn () => $tradeService->accept($trade))->toThrow(InvalidArgumentException::class, 'locked event');
});

// ============================================================================
// No-Duplicates Rule Enforcement
// ============================================================================

test('trade is rejected when it would create duplicate entities on a team', function () {
    Notification::fake();

    $scenario = createTradeScenario(['no_duplicates' => true]);
    $tradeService = app(TradeService::class);

    // Manually add driver 0 to team 2 (creating a scenario where accepting would duplicate)
    FantasyTeamRoster::create([
        'fantasy_team_id' => $scenario['team2']->id,
        'entity_type' => 'driver',
        'entity_id' => $scenario['drivers'][0]->id,
        'in_seat' => false,
        'acquired_at' => now(),
    ]);

    // Team 1 gives driver 0 to team 2 — team 2 already has driver 0
    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    expect(fn () => $tradeService->accept($trade))->toThrow(InvalidArgumentException::class, 'already has this');
});

test('trade succeeds when no-duplicates rule is disabled', function () {
    Notification::fake();

    $scenario = createTradeScenario(['no_duplicates' => false, 'trade_approval_required' => true]);
    $tradeService = app(TradeService::class);

    // Add driver 0 to team 2 as well
    FantasyTeamRoster::create([
        'fantasy_team_id' => $scenario['team2']->id,
        'entity_type' => 'driver',
        'entity_id' => $scenario['drivers'][0]->id,
        'in_seat' => false,
        'acquired_at' => now(),
    ]);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    // Should not throw when no_duplicates is false
    $tradeService->accept($trade);

    expect($trade->fresh()->status)->toBe('completed');
});

// ============================================================================
// Commissioner Approval Flow
// ============================================================================

test('commissioner can accept a pending trade via controller', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    $this->actingAs($scenario['user1']) // commissioner
        ->post(route('leagues.trades.accept', [$scenario['league']->slug, $trade->id]))
        ->assertRedirect();

    expect($trade->fresh()->status)->toBe('completed');
});

test('receiver can accept a pending trade via controller', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    $this->actingAs($scenario['user2']) // receiver
        ->post(route('leagues.trades.accept', [$scenario['league']->slug, $trade->id]))
        ->assertRedirect();

    expect($trade->fresh()->status)->toBe('completed');
});

test('non-involved user cannot accept a trade', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('leagues.trades.accept', [$scenario['league']->slug, $trade->id]))
        ->assertForbidden();
});

test('commissioner can reject a pending trade', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    $this->actingAs($scenario['user1'])
        ->post(route('leagues.trades.reject', [$scenario['league']->slug, $trade->id]))
        ->assertRedirect();

    expect($trade->fresh()->status)->toBe('rejected');
});

test('accepting a trade transfers roster entries between teams', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    $tradeService->accept($trade);

    // Team 1 should now have driver 2, not driver 0
    expect(FantasyTeamRoster::where('fantasy_team_id', $scenario['team1']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][2]->id)->exists())->toBeTrue();
    expect(FantasyTeamRoster::where('fantasy_team_id', $scenario['team1']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)->exists())->toBeFalse();

    // Team 2 should now have driver 0, not driver 2
    expect(FantasyTeamRoster::where('fantasy_team_id', $scenario['team2']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][0]->id)->exists())->toBeTrue();
    expect(FantasyTeamRoster::where('fantasy_team_id', $scenario['team2']->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $scenario['drivers'][2]->id)->exists())->toBeFalse();
});

test('accepting a trade notifies the initiator', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    $tradeService->accept($trade);

    Notification::assertSentTo($scenario['user1'], TradeResolvedNotification::class);
});

test('rejecting a trade notifies the initiator', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    $tradeService->reject($trade);

    Notification::assertSentTo($scenario['user1'], TradeResolvedNotification::class);
});

test('cannot accept an already completed trade', function () {
    Notification::fake();

    $scenario = createTradeScenario();
    $tradeService = app(TradeService::class);

    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        $scenario['team2'],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
    );

    $tradeService->accept($trade);

    expect(fn () => $tradeService->accept($trade->fresh()))->toThrow(InvalidArgumentException::class, 'not pending');
});

test('free agent trade moves entity to/from free agent pool', function () {
    Notification::fake();

    $scenario = createTradeScenario(['trade_approval_required' => false]);

    // Add a driver to free agent pool
    $freeDriver = Driver::create([
        'franchise_id' => $scenario['franchise']->id,
        'name' => 'Free Agent Driver',
        'slug' => 'free-agent-driver-' . uniqid(),
        'is_active' => true,
    ]);

    FreeAgentPool::create([
        'league_id' => $scenario['league']->id,
        'entity_type' => 'driver',
        'entity_id' => $freeDriver->id,
        'added_at' => now(),
    ]);

    $tradeService = app(TradeService::class);

    // Team 1 drops driver 0, picks up free agent
    $trade = $tradeService->propose(
        $scenario['league'],
        $scenario['team1'],
        null, // null receiver = free agent pool
        [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
        [['entity_type' => 'driver', 'entity_id' => $freeDriver->id]],
    );

    expect($trade->status)->toBe('completed');

    // Team 1 should have free agent driver
    expect(FantasyTeamRoster::where('fantasy_team_id', $scenario['team1']->id)
        ->where('entity_id', $freeDriver->id)->exists())->toBeTrue();

    // Driver 0 should be in free agent pool
    expect(FreeAgentPool::where('league_id', $scenario['league']->id)
        ->where('entity_id', $scenario['drivers'][0]->id)->exists())->toBeTrue();
});

// ============================================================================
// TradeController@index
// ============================================================================

test('trade index page renders for authenticated user with a team', function () {
    $scenario = createTradeScenario();

    $this->actingAs($scenario['user1'])
        ->get(route('leagues.trades.index', $scenario['league']->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Trades/Index')
            ->has('league')
            ->has('trades')
            ->where('myTeam.id', $scenario['team1']->id)
            ->where('isCommissioner', true)
            ->has('tradeApprovalRequired')
        );
});

test('trade index page shows auto-created myTeam for new member', function () {
    $scenario = createTradeScenario();

    $newMember = User::factory()->create();
    LeagueMember::create([
        'league_id' => $scenario['league']->id,
        'user_id' => $newMember->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $autoTeam = FantasyTeam::where('league_id', $scenario['league']->id)->where('user_id', $newMember->id)->first();

    $this->actingAs($newMember)
        ->get(route('leagues.trades.index', $scenario['league']->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Trades/Index')
            ->where('myTeam.id', $autoTeam->id)
            ->where('isCommissioner', false)
        );
});

test('trade index page requires authentication', function () {
    $scenario = createTradeScenario();

    $this->get(route('leagues.trades.index', $scenario['league']->slug))
        ->assertRedirect(route('login'));
});

// ============================================================================
// TradeController@create
// ============================================================================

test('trade create page renders with correct props', function () {
    $scenario = createTradeScenario();

    $this->actingAs($scenario['user1'])
        ->get(route('leagues.trades.create', $scenario['league']->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Trades/Create')
            ->has('league')
            ->where('myTeam.id', $scenario['team1']->id)
            ->has('otherTeams')
            ->has('freeAgents')
        );
});

test('trade create page renders for new member with auto-created team', function () {
    $scenario = createTradeScenario();

    $newMember = User::factory()->create();
    LeagueMember::create([
        'league_id' => $scenario['league']->id,
        'user_id' => $newMember->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $autoTeam = FantasyTeam::where('league_id', $scenario['league']->id)->where('user_id', $newMember->id)->first();

    $this->actingAs($newMember)
        ->get(route('leagues.trades.create', $scenario['league']->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Trades/Create')
            ->has('league')
            ->where('myTeam.id', $autoTeam->id)
        );
});

// ============================================================================
// TradeController@store
// ============================================================================

test('trade store via controller creates trade and redirects', function () {
    Notification::fake();

    $scenario = createTradeScenario();

    $this->actingAs($scenario['user1'])
        ->post(route('leagues.trades.store', $scenario['league']->slug), [
            'receiver_team_id' => $scenario['team2']->id,
            'giving' => [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
            'receiving' => [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][2]->id]],
        ])
        ->assertRedirect(route('leagues.trades.index', $scenario['league']->slug))
        ->assertSessionHas('success', 'Trade proposal submitted.');

    expect(Trade::where('league_id', $scenario['league']->id)
        ->where('initiator_team_id', $scenario['team1']->id)
        ->where('receiver_team_id', $scenario['team2']->id)
        ->exists())->toBeTrue();
});

test('trade store with null receiver_team_id for free agent trade', function () {
    Notification::fake();

    $scenario = createTradeScenario(['trade_approval_required' => false]);

    $freeDriver = Driver::create([
        'franchise_id' => $scenario['franchise']->id,
        'name' => 'Free Agent Store Driver',
        'slug' => 'free-agent-store-driver-' . uniqid(),
        'is_active' => true,
    ]);

    FreeAgentPool::create([
        'league_id' => $scenario['league']->id,
        'entity_type' => 'driver',
        'entity_id' => $freeDriver->id,
        'added_at' => now(),
    ]);

    $this->actingAs($scenario['user1'])
        ->post(route('leagues.trades.store', $scenario['league']->slug), [
            'receiver_team_id' => null,
            'giving' => [['entity_type' => 'driver', 'entity_id' => $scenario['drivers'][0]->id]],
            'receiving' => [['entity_type' => 'driver', 'entity_id' => $freeDriver->id]],
        ])
        ->assertRedirect(route('leagues.trades.index', $scenario['league']->slug))
        ->assertSessionHas('success', 'Trade proposal submitted.');

    // Free agent trade with no approval should complete immediately
    expect(Trade::where('league_id', $scenario['league']->id)
        ->where('initiator_team_id', $scenario['team1']->id)
        ->whereNull('receiver_team_id')
        ->where('status', 'completed')
        ->exists())->toBeTrue();
});
