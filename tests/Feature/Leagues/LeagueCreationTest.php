<?php

use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use App\Models\User;

test('guests cannot access the create league page', function () {
    $this->get(route('leagues.create'))->assertRedirect(route('login'));
});

test('authenticated users can access the create league page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('leagues.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Leagues/Settings/Create'));
});

test('authenticated users can create a league', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('leagues.store'), [
            'franchise_id' => $franchise->id,
            'name' => 'My Test League',
            'description' => null,
            'max_teams' => null,
            'visibility' => 'public',
            'join_policy' => 'open',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('leagues', [
        'name' => 'My Test League',
        'commissioner_id' => $user->id,
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
    ]);

    $league = League::where('name', 'My Test League')->first();
    $this->assertDatabaseHas('league_members', [
        'league_id' => $league->id,
        'user_id' => $user->id,
        'role' => 'commissioner',
    ]);
});

test('invite-only leagues get an invite code on creation', function () {
    $franchise = Franchise::factory()->create();
    Season::factory()->create(['franchise_id' => $franchise->id, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('leagues.store'), [
            'franchise_id' => $franchise->id,
            'name' => 'Invite League',
            'visibility' => 'private',
            'join_policy' => 'invite_only',
        ])
        ->assertRedirect();

    $league = League::where('name', 'Invite League')->first();
    expect($league->invite_code)->not->toBeNull();
});

test('league creation validates required fields', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('leagues.store'), [])
        ->assertSessionHasErrors(['franchise_id', 'name', 'visibility', 'join_policy']);
});
