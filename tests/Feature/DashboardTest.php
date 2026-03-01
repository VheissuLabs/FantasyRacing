<?php

use App\Models\Country;
use App\Models\Event;
use App\Models\Track;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows next race countdown when an event exists', function () {
    $user = User::factory()->create();

    $country = Country::create([
        'name' => 'Monaco',
        'iso2' => fake()->unique()->lexify('??'),
        'iso3' => fake()->unique()->lexify('???'),
        'nationality' => 'Monégasque',
        'region' => 'Europe',
        'subregion' => 'Western',
    ]);

    $track = Track::factory()->create([
        'country_id' => $country->id,
        'name' => 'Circuit de Monaco',
    ]);

    $event = Event::factory()->create([
        'track_id' => $track->id,
        'type' => 'race',
        'status' => 'scheduled',
        'scheduled_at' => now()->addDays(10),
        'round' => 5,
        'name' => 'Monaco Grand Prix',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('nextEvent')
            ->where('nextEvent.id', $event->id)
            ->where('nextEvent.name', 'Monaco Grand Prix')
            ->where('nextEvent.round', 5)
            ->has('nextEvent.track')
            ->where('nextEvent.track.name', 'Circuit de Monaco')
        );
});

test('dashboard shows next race regardless of status', function () {
    $user = User::factory()->create();

    $country = Country::create([
        'name' => 'Australia',
        'iso2' => fake()->unique()->lexify('??'),
        'iso3' => fake()->unique()->lexify('???'),
        'nationality' => 'Australian',
        'region' => 'Oceania',
        'subregion' => 'Australasia',
    ]);

    $track = Track::factory()->create([
        'country_id' => $country->id,
        'name' => 'Albert Park',
    ]);

    $event = Event::factory()->create([
        'track_id' => $track->id,
        'type' => 'race',
        'status' => 'completed',
        'scheduled_at' => now()->addDays(7),
        'round' => 1,
        'name' => 'Australian Grand Prix',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('nextEvent')
            ->where('nextEvent.id', $event->id)
        );
});

test('dashboard renders gracefully with no upcoming events', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('nextEvent', null)
        );
});
