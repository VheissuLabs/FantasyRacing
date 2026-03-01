<?php

use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use App\Models\User;

function makeRequestLeague(): array
{
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'join_policy' => 'request',
        'visibility' => 'public',
    ]);

    return ['league' => $league, 'commissioner' => $commissioner];
}

test('commissioner sees pending join requests on show page', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeRequestLeague();

    $requester = User::factory()->create();
    $league->joinRequests()->create([
        'user_id' => $requester->id,
        'message' => 'Please let me in!',
        'status' => 'pending',
    ]);

    $this->actingAs($commissioner)
        ->get(route('leagues.show', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Show')
            ->has('joinRequests', 1)
            ->where('joinRequests.0.user.name', $requester->name)
        );
});

test('non-commissioner sees empty join requests', function () {
    ['league' => $league] = makeRequestLeague();

    $requester = User::factory()->create();
    $league->joinRequests()->create([
        'user_id' => $requester->id,
        'status' => 'pending',
    ]);

    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('leagues.show', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Show')
            ->has('joinRequests', 0)
        );
});

test('only pending requests are included', function () {
    ['league' => $league, 'commissioner' => $commissioner] = makeRequestLeague();

    $league->joinRequests()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'pending',
    ]);
    $league->joinRequests()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'approved',
    ]);
    $league->joinRequests()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'rejected',
    ]);

    $this->actingAs($commissioner)
        ->get(route('leagues.show', $league->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Show')
            ->has('joinRequests', 1)
        );
});
