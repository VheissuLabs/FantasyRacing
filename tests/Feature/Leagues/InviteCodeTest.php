<?php

use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use App\Models\User;

beforeEach(function () {
    $this->franchise = Franchise::factory()->create();
    $this->season = Season::factory()->create(['franchise_id' => $this->franchise->id]);
    $this->commissioner = User::factory()->create();
    $this->league = League::factory()->create([
        'franchise_id' => $this->franchise->id,
        'season_id' => $this->season->id,
        'commissioner_id' => $this->commissioner->id,
        'join_policy' => 'invite_only',
        'invite_code' => 'TESTCODE',
    ]);
});

test('invite code landing page renders for valid code', function () {
    $this->get('/join/TESTCODE')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Invite/Show')
            ->has('league')
            ->where('league.name', $this->league->name)
        );
});

test('invite code landing page returns 404 for invalid code', function () {
    $this->get('/join/INVALID')
        ->assertNotFound();
});

test('authenticated user can join league via invite code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/join/TESTCODE')
        ->assertRedirect();

    expect($this->league->members()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('guest cannot join via invite code', function () {
    $this->post('/join/TESTCODE')
        ->assertRedirect('/login');
});

test('already-member cannot join again via invite code', function () {
    $user = User::factory()->create();
    $this->league->members()->create([
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->post('/join/TESTCODE')
        ->assertStatus(422);
});

test('cannot join full league via invite code', function () {
    $this->league->update(['max_teams' => 1]);
    $this->league->members()->create([
        'user_id' => User::factory()->create()->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/join/TESTCODE')
        ->assertStatus(422);
});

test('commissioner can regenerate invite code', function () {
    $oldCode = $this->league->invite_code;

    $this->actingAs($this->commissioner)
        ->post("/leagues/{$this->league->slug}/settings/regenerate-invite-code")
        ->assertRedirect();

    $this->league->refresh();
    expect($this->league->invite_code)->not->toBe($oldCode)
        ->and($this->league->invite_code)->toHaveLength(8);
});

test('non-commissioner cannot regenerate invite code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/leagues/{$this->league->slug}/settings/regenerate-invite-code")
        ->assertForbidden();
});

test('invite code is auto-generated when switching to invite_only', function () {
    $league = League::factory()->create([
        'franchise_id' => $this->franchise->id,
        'season_id' => $this->season->id,
        'commissioner_id' => $this->commissioner->id,
        'join_policy' => 'open',
        'invite_code' => null,
    ]);

    $this->actingAs($this->commissioner)
        ->put("/leagues/{$league->slug}/settings", [
            'name' => $league->name,
            'description' => $league->description,
            'max_teams' => $league->max_teams,
            'visibility' => $league->visibility,
            'join_policy' => 'invite_only',
            'rules' => $league->rules,
        ])
        ->assertRedirect();

    $league->refresh();
    expect($league->invite_code)->not->toBeNull()
        ->and($league->invite_code)->toHaveLength(8);
});

test('invite code is cleared when switching away from invite_only', function () {
    $this->actingAs($this->commissioner)
        ->put("/leagues/{$this->league->slug}/settings", [
            'name' => $this->league->name,
            'description' => $this->league->description,
            'max_teams' => $this->league->max_teams,
            'visibility' => $this->league->visibility,
            'join_policy' => 'open',
            'rules' => $this->league->rules,
        ])
        ->assertRedirect();

    $this->league->refresh();
    expect($this->league->invite_code)->toBeNull();
});

test('invite code url is passed to commissioner on league show page', function () {
    $this->actingAs($this->commissioner)
        ->get("/leagues/{$this->league->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('inviteCodeUrl', url('/join/TESTCODE'))
        );
});

test('invite code url is not passed to non-commissioner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get("/leagues/{$this->league->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('inviteCodeUrl', null)
        );
});
