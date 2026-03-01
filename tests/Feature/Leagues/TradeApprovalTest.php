<?php

use App\Models\FantasyTeam;
use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use App\Models\Trade;
use App\Models\User;

function makeTradeApprovalScenario(array $rules = ['trade_approval_required' => true]): array
{
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'rules' => $rules,
    ]);

    $initiatorUser = User::factory()->create();
    $receiverUser = User::factory()->create();

    $initiatorTeam = FantasyTeam::factory()->create([
        'league_id' => $league->id,
        'user_id' => $initiatorUser->id,
    ]);

    $receiverTeam = FantasyTeam::factory()->create([
        'league_id' => $league->id,
        'user_id' => $receiverUser->id,
    ]);

    $trade = Trade::factory()->create([
        'league_id' => $league->id,
        'initiator_team_id' => $initiatorTeam->id,
        'receiver_team_id' => $receiverTeam->id,
        'status' => 'pending',
    ]);

    return [
        'league' => $league,
        'commissioner' => $commissioner,
        'initiatorUser' => $initiatorUser,
        'receiverUser' => $receiverUser,
        'initiatorTeam' => $initiatorTeam,
        'receiverTeam' => $receiverTeam,
        'trade' => $trade,
    ];
}

test('commissioner sees isCommissioner prop on trades index', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeTradeApprovalScenario();

    $this->actingAs($commissioner)
        ->get(route('leagues.trades.index', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Trades/Index')
            ->where('isCommissioner', true)
        );
});

test('non-commissioner sees isCommissioner as false', function () {
    ['league' => $league, 'initiatorUser' => $user] = makeTradeApprovalScenario();

    $this->actingAs($user)
        ->get(route('leagues.trades.index', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isCommissioner', false)
        );
});

test('tradeApprovalRequired flag is passed to frontend', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeTradeApprovalScenario(['trade_approval_required' => true]);

    $this->actingAs($commissioner)
        ->get(route('leagues.trades.index', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tradeApprovalRequired', true)
        );
});

test('tradeApprovalRequired is false when rule is disabled', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeTradeApprovalScenario(['trade_approval_required' => false]);

    $this->actingAs($commissioner)
        ->get(route('leagues.trades.index', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tradeApprovalRequired', false)
        );
});

test('commissioner can approve a pending trade', function () {
    ['league' => $league, 'commissioner' => $commissioner, 'trade' => $trade] = makeTradeApprovalScenario();

    $this->actingAs($commissioner)
        ->post(route('leagues.trades.accept', [$league->slug, $trade->id]))
        ->assertRedirect();

    $trade->refresh();
    expect($trade->status)->toBe('completed');
    expect($trade->resolved_at)->not->toBeNull();
});

test('commissioner can reject a pending trade', function () {
    ['league' => $league, 'commissioner' => $commissioner, 'trade' => $trade] = makeTradeApprovalScenario();

    $this->actingAs($commissioner)
        ->post(route('leagues.trades.reject', [$league->slug, $trade->id]))
        ->assertRedirect();

    $trade->refresh();
    expect($trade->status)->toBe('rejected');
    expect($trade->resolved_at)->not->toBeNull();
});

test('non-commissioner cannot approve another team trade', function () {
    ['league' => $league, 'trade' => $trade] = makeTradeApprovalScenario();

    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('leagues.trades.accept', [$league->slug, $trade->id]))
        ->assertForbidden();

    $trade->refresh();
    expect($trade->status)->toBe('pending');
});
