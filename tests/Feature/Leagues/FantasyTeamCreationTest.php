<?php

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;

test('guests cannot access the create team page', function () {
    $league = League::factory()->create();

    $this->get(route('leagues.teams.create', $league->slug))
        ->assertRedirect(route('login'));
});

test('league members can access the create team page', function () {
    $league = League::factory()->create();
    $user = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('leagues.teams.create', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Teams/Create')
            ->has('league')
        );
});

test('non-members cannot access the create team page', function () {
    $league = League::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('leagues.teams.create', $league->slug))
        ->assertForbidden();
});

test('members who already have a team cannot access the create team page', function () {
    $league = League::factory()->create();
    $user = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);
    FantasyTeam::factory()->create(['league_id' => $league->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('leagues.teams.create', $league->slug))
        ->assertStatus(409);
});

test('league members can create a fantasy team', function () {
    $league = League::factory()->create();
    $user = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->post(route('leagues.teams.store', $league->slug), [
            'name' => 'Turbo Racing',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('fantasy_teams', [
        'league_id' => $league->id,
        'user_id' => $user->id,
        'name' => 'Turbo Racing',
    ]);
});

test('team creation validates required fields', function () {
    $league = League::factory()->create();
    $user = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->post(route('leagues.teams.store', $league->slug), [])
        ->assertSessionHasErrors(['name']);
});

test('members cannot create a second team in the same league', function () {
    $league = League::factory()->create();
    $user = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);
    FantasyTeam::factory()->create(['league_id' => $league->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('leagues.teams.store', $league->slug), [
            'name' => 'Second Team',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('fantasy_teams', ['name' => 'Second Team']);
});

test('league show page includes fantasy team for members', function () {
    $league = League::factory()->create(['visibility' => 'public']);
    $user = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);
    $team = FantasyTeam::factory()->create(['league_id' => $league->id, 'user_id' => $user->id, 'name' => 'My Team']);

    $this->actingAs($user)
        ->get(route('leagues.show', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Show')
            ->where('fantasyTeam.id', $team->id)
            ->where('fantasyTeam.name', 'My Team')
        );
});

test('league show page returns null fantasy team for members without one', function () {
    $league = League::factory()->create(['visibility' => 'public']);
    $user = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('leagues.show', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Show')
            ->where('fantasyTeam', null)
        );
});
