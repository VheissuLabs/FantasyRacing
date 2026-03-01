<?php

use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use App\Models\User;

function makeLeagueWithCommissioner(): array
{
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'join_policy' => 'open',
    ]);

    return ['league' => $league, 'commissioner' => $commissioner];
}

test('commissioner can view settings page', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeLeagueWithCommissioner();

    $this->actingAs($commissioner)
        ->get(route('leagues.settings', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Settings/Edit')
            ->has('league')
            ->where('league.id', $league->id)
        );
});

test('non-commissioner gets 403 on settings page', function () {
    ['league' => $league] = makeLeagueWithCommissioner();

    $this->actingAs(User::factory()->create())
        ->get(route('leagues.settings', $league->slug))
        ->assertForbidden();
});

test('guest is redirected to login', function () {
    ['league' => $league] = makeLeagueWithCommissioner();

    $this->get(route('leagues.settings', $league->slug))
        ->assertRedirect(route('login'));
});

test('commissioner can update name and description', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeLeagueWithCommissioner();

    $this->actingAs($commissioner)
        ->put(route('leagues.settings.update', $league->slug), [
            'name' => 'Updated Name',
            'description' => 'New description',
            'max_teams' => null,
            'visibility' => 'public',
            'join_policy' => 'open',
            'rules' => [],
        ])
        ->assertRedirect();

    $league->refresh();
    expect($league->name)->toBe('Updated Name');
    expect($league->description)->toBe('New description');
});

test('commissioner can update visibility and join policy', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeLeagueWithCommissioner();

    $this->actingAs($commissioner)
        ->put(route('leagues.settings.update', $league->slug), [
            'name' => $league->name,
            'description' => $league->description,
            'max_teams' => null,
            'visibility' => 'private',
            'join_policy' => 'invite_only',
            'rules' => [],
        ])
        ->assertRedirect();

    $league->refresh();
    expect($league->visibility)->toBe('private');
    expect($league->join_policy)->toBe('invite_only');
});

test('commissioner can update rules', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeLeagueWithCommissioner();

    $this->actingAs($commissioner)
        ->put(route('leagues.settings.update', $league->slug), [
            'name' => $league->name,
            'description' => $league->description,
            'max_teams' => 10,
            'visibility' => 'public',
            'join_policy' => 'open',
            'rules' => [
                'no_duplicates' => true,
                'trade_approval_required' => false,
                'trades_enabled' => true,
                'max_roster_size' => 5,
            ],
        ])
        ->assertRedirect();

    $league->refresh();
    expect($league->max_teams)->toBe(10);
    expect($league->rule('no_duplicates'))->toBeTrue();
    expect($league->rule('trade_approval_required'))->toBeFalse();
    expect($league->rule('trades_enabled'))->toBeTrue();
    expect($league->rule('max_roster_size'))->toBe(5);
});

test('non-commissioner cannot update settings', function () {
    ['league' => $league] = makeLeagueWithCommissioner();

    $this->actingAs(User::factory()->create())
        ->put(route('leagues.settings.update', $league->slug), [
            'name' => 'Hacked',
            'visibility' => 'public',
            'join_policy' => 'open',
            'rules' => [],
        ])
        ->assertForbidden();
});

test('validation rejects invalid data', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeLeagueWithCommissioner();

    $this->actingAs($commissioner)
        ->put(route('leagues.settings.update', $league->slug), [
            'name' => '',
            'visibility' => 'invalid',
            'join_policy' => 'invalid',
            'rules' => [],
        ])
        ->assertSessionHasErrors(['name', 'visibility', 'join_policy']);
});
