<?php

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;

test('fantasy team is auto-created when a user joins a league', function () {
    $league = League::factory()->create();
    $user = User::factory()->create();

    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);

    $this->assertDatabaseHas('fantasy_teams', [
        'league_id' => $league->id,
        'user_id' => $user->id,
        'name' => $user->name.'\'s Team',
    ]);
});

test('members cannot create a second team in the same league', function () {
    $league = League::factory()->create();
    $user = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->post(route('leagues.teams.store', $league->slug), [
            'name' => 'Second Team',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('fantasy_teams', ['name' => 'Second Team']);
});

test('league show page includes auto-created fantasy team for members', function () {
    $league = League::factory()->create(['visibility' => 'public']);
    $user = User::factory()->create();
    LeagueMember::create(['league_id' => $league->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);

    $team = FantasyTeam::where('league_id', $league->id)->where('user_id', $user->id)->first();

    $this->actingAs($user)
        ->get(route('leagues.show', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Show')
            ->where('fantasyTeam.id', $team->id)
            ->where('fantasyTeam.name', $user->name.'\'s Team')
        );
});
