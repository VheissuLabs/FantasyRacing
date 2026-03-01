<?php

use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use App\Models\User;

test('league index renders', function () {
    $this->get(route('leagues.index'))->assertOk()->assertInertia(
        fn ($page) => $page->component('Leagues/Index')
    );
});

test('open leagues appear in the directory', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $this->get(route('leagues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Index')
            ->has('leagues.data', fn ($data) => $data->where('0.id', $league->id))
        );
});

test('closed leagues do not appear in the directory', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'private',
        'is_active' => true,
    ]);

    $this->get(route('leagues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Index')
            ->has('leagues.data', 0)
        );
});

test('guests can view open league detail', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
    ]);

    $this->get(route('leagues.show', $league->slug))->assertOk();
});

test('guests are forbidden from closed league detail', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'private',
    ]);

    $this->get(route('leagues.show', $league->slug))->assertForbidden();
});

test('authenticated users can view closed league detail', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $league = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'private',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('leagues.show', $league->slug))
        ->assertOk();
});

test('join_policy filter returns only matching leagues', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id]);
    $commissioner = User::factory()->create();

    $openLeague = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'join_policy' => 'open',
        'is_active' => true,
    ]);

    League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'join_policy' => 'request',
        'is_active' => true,
    ]);

    $this->get(route('leagues.index', ['join_policy' => 'open']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leagues/Index')
            ->has('leagues.data', 1)
            ->where('leagues.data.0.id', $openLeague->id)
        );
});

test('multiple filters combine correctly', function () {
    $franchise = Franchise::factory()->create();
    $season = Season::factory()->create(['franchise_id' => $franchise->id, 'year' => 2025]);
    $commissioner = User::factory()->create();

    $matchingLeague = League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'join_policy' => 'open',
        'is_active' => true,
    ]);

    League::factory()->create([
        'franchise_id' => $franchise->id,
        'season_id' => $season->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'join_policy' => 'request',
        'is_active' => true,
    ]);

    $otherFranchise = Franchise::factory()->create();
    $otherSeason = Season::factory()->create(['franchise_id' => $otherFranchise->id]);

    League::factory()->create([
        'franchise_id' => $otherFranchise->id,
        'season_id' => $otherSeason->id,
        'commissioner_id' => $commissioner->id,
        'visibility' => 'public',
        'join_policy' => 'open',
        'is_active' => true,
    ]);

    $this->get(route('leagues.index', [
        'franchise' => $franchise->slug,
        'season' => 2025,
        'join_policy' => 'open',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('leagues.data', 1)
            ->where('leagues.data.0.id', $matchingLeague->id)
        );
});
