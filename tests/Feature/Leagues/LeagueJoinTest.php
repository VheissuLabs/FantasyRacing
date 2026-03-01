<?php

use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use App\Models\User;

function makeOpenLeague(): League
{
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    return League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'join_policy' => 'open',
    ]);
}

test('authenticated user can join an open league', function () {
    $league = makeOpenLeague();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('leagues.join', $league->slug))
        ->assertRedirect(route('leagues.show', $league->slug));

    $this->assertDatabaseHas('league_members', [
        'league_id' => $league->id,
        'user_id' => $user->id,
    ]);
});

test('user cannot join a non-open league directly', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'join_policy' => 'invite_only',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('leagues.join', $league->slug))
        ->assertForbidden();
});

test('user cannot join a league they are already a member of', function () {
    $league = makeOpenLeague();
    $user = User::factory()->create();

    $league->members()->create(['user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->post(route('leagues.join', $league->slug))
        ->assertStatus(422);
});

test('user can submit a join request to a request-policy league', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'join_policy' => 'request',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('leagues.join-requests.store', $league->slug))
        ->assertRedirect(route('leagues.show', $league->slug));

    $this->assertDatabaseHas('league_join_requests', [
        'league_id' => $league->id,
        'user_id' => $user->id,
        'status' => 'pending',
    ]);
});

test('commissioner can approve a join request', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'join_policy' => 'request',
    ]);

    $requester = User::factory()->create();
    $joinRequest = $league->joinRequests()->create([
        'user_id' => $requester->id,
        'status' => 'pending',
    ]);

    $this->actingAs($commissioner)
        ->post(route('leagues.join-requests.approve', [$league->slug, $joinRequest->id]))
        ->assertRedirect();

    $this->assertDatabaseHas('league_members', [
        'league_id' => $league->id,
        'user_id' => $requester->id,
    ]);
    $this->assertDatabaseHas('league_join_requests', [
        'id' => $joinRequest->id,
        'status' => 'approved',
    ]);
});

test('non-commissioner cannot approve a join request', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'join_policy' => 'request',
    ]);

    $requester = User::factory()->create();
    $joinRequest = $league->joinRequests()->create([
        'user_id' => $requester->id,
        'status' => 'pending',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('leagues.join-requests.approve', [$league->slug, $joinRequest->id]))
        ->assertForbidden();
});
