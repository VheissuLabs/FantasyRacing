<?php

use App\Models\Franchise;
use App\Models\League;
use App\Models\LeagueInvite;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Str;

function makeLeague(): League
{
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    return League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
    ]);
}

test('invite show page renders for a valid pending invite', function () {
    $league = makeLeague();
    $token = Str::random(32);

    LeagueInvite::factory()->create([
        'league_id' => $league->id,
        'invited_by' => $league->commissioner_id,
        'token' => $token,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    $this->get(route('invites.show', $token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Invites/Show')
            ->where('expired', false)
            ->where('alreadyUsed', false)
        );
});

test('invite show page indicates expired invite', function () {
    $league = makeLeague();
    $token = Str::random(32);

    LeagueInvite::factory()->create([
        'league_id' => $league->id,
        'invited_by' => $league->commissioner_id,
        'token' => $token,
        'status' => 'pending',
        'expires_at' => now()->subDay(),
    ]);

    $this->get(route('invites.show', $token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('expired', true)
        );
});

test('authenticated user can accept a valid invite', function () {
    $league = makeLeague();
    $token = Str::random(32);
    $user = User::factory()->create();

    LeagueInvite::factory()->create([
        'league_id' => $league->id,
        'invited_by' => $league->commissioner_id,
        'token' => $token,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($user)
        ->post(route('invites.accept', $token))
        ->assertRedirect(route('leagues.show', $league->slug));

    $this->assertDatabaseHas('league_members', [
        'league_id' => $league->id,
        'user_id' => $user->id,
    ]);
    $this->assertDatabaseHas('league_invites', [
        'token' => $token,
        'status' => 'accepted',
    ]);
});

test('commissioner can send an invite', function () {
    $league = makeLeague();

    $this->actingAs($league->commissioner)
        ->post(route('leagues.invites.store', $league->slug), ['email' => 'test@example.com'])
        ->assertRedirect();

    $this->assertDatabaseHas('league_invites', [
        'league_id' => $league->id,
        'email' => 'test@example.com',
        'status' => 'pending',
    ]);
});

test('non-commissioner cannot send invites', function () {
    $league = makeLeague();

    $this->actingAs(User::factory()->create())
        ->post(route('leagues.invites.store', $league->slug), ['email' => 'test@example.com'])
        ->assertForbidden();
});

test('league show page includes pending invites for commissioner', function () {
    $league = makeLeague();

    LeagueInvite::factory()->create([
        'league_id' => $league->id,
        'invited_by' => $league->commissioner_id,
        'email' => 'pending@example.com',
        'token' => Str::random(32),
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    // Expired invite should not appear
    LeagueInvite::factory()->create([
        'league_id' => $league->id,
        'invited_by' => $league->commissioner_id,
        'email' => 'accepted@example.com',
        'token' => Str::random(32),
        'status' => 'accepted',
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($league->commissioner)
        ->get(route('leagues.show', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isCommissioner', true)
            ->has('invites', 1)
            ->where('invites.0.email', 'pending@example.com')
        );
});

test('league show page hides invites for non-commissioners', function () {
    $league = makeLeague();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('leagues.show', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isCommissioner', false)
            ->where('invites', [])
        );
});
