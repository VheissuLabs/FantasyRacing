<?php

use App\Models\Constructor;
use App\Models\Country;
use App\Models\Driver;
use App\Models\DriverSeasonStat;
use App\Models\Event;
use App\Models\EventResult;
use App\Models\Franchise;
use App\Models\Season;
use App\Models\SeasonDriver;
use App\Models\Track;

beforeEach(function () {
    $this->country = Country::create([
        'name' => 'Testland',
        'iso2' => fake()->lexify('??'),
        'iso3' => fake()->lexify('???'),
        'nationality' => 'Testlandian',
        'region' => 'Europe',
        'subregion' => 'Northern',
    ]);
    $this->franchise = Franchise::factory()->create();
});

test('driver profile page renders with correct component', function () {
    Season::factory()->active()->create(['franchise_id' => $this->franchise->id]);
    $driver = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);

    $this->get(route('drivers.show', $driver->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Drivers/Show')
            ->has('driver')
            ->has('careerSummary')
            ->has('availableSeasons')
        );
});

test('career summary aggregates correctly across seasons', function () {
    $driver = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);
    Season::factory()->active()->create(['franchise_id' => $this->franchise->id]);

    $season1 = Season::factory()->create(['franchise_id' => $this->franchise->id, 'year' => 2024]);
    $season2 = Season::factory()->create(['franchise_id' => $this->franchise->id, 'year' => 2025]);
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);

    DriverSeasonStat::factory()->create([
        'driver_id' => $driver->id,
        'season_id' => $season1->id,
        'constructor_id' => $constructor->id,
        'races_entered' => 20,
        'wins' => 3,
        'podiums' => 8,
        'poles' => 4,
        'fastest_laps' => 2,
        'championship_position' => 2,
    ]);

    DriverSeasonStat::factory()->create([
        'driver_id' => $driver->id,
        'season_id' => $season2->id,
        'constructor_id' => $constructor->id,
        'races_entered' => 22,
        'wins' => 5,
        'podiums' => 10,
        'poles' => 6,
        'fastest_laps' => 3,
        'championship_position' => 1,
    ]);

    $this->get(route('drivers.show', $driver->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Drivers/Show')
            ->where('careerSummary.seasons', 2)
            ->where('careerSummary.races_entered', 42)
            ->where('careerSummary.wins', 8)
            ->where('careerSummary.podiums', 18)
            ->where('careerSummary.poles', 10)
            ->where('careerSummary.fastest_laps', 5)
            ->where('careerSummary.best_championship', 1)
        );
});

test('season stats include constructor info', function () {
    $driver = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);
    Season::factory()->active()->create(['franchise_id' => $this->franchise->id]);

    $season = Season::factory()->create(['franchise_id' => $this->franchise->id, 'year' => 2025]);
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id, 'name' => 'Red Bull Racing']);

    DriverSeasonStat::factory()->create([
        'driver_id' => $driver->id,
        'season_id' => $season->id,
        'constructor_id' => $constructor->id,
    ]);

    $this->get(route('drivers.show', $driver->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('seasonStats', 1)
            ->where('seasonStats.0.constructor.name', 'Red Bull Racing')
        );
});

test('recent results are limited to 10', function () {
    $driver = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);
    Season::factory()->active()->create(['franchise_id' => $this->franchise->id]);

    $season = Season::factory()->create(['franchise_id' => $this->franchise->id]);
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);
    $track = Track::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);

    for ($i = 0; $i < 15; $i++) {
        $event = Event::factory()->completed()->create([
            'season_id' => $season->id,
            'track_id' => $track->id,
        ]);

        EventResult::factory()->create([
            'event_id' => $event->id,
            'driver_id' => $driver->id,
            'constructor_id' => $constructor->id,
        ]);
    }

    $this->get(route('drivers.show', $driver->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('recentResults', 10));
});

test('season page renders with season-specific data', function () {
    $driver = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);
    $season = Season::factory()->create(['franchise_id' => $this->franchise->id, 'year' => 2025, 'name' => '2025']);
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);

    DriverSeasonStat::factory()->create([
        'driver_id' => $driver->id,
        'season_id' => $season->id,
        'constructor_id' => $constructor->id,
        'wins' => 7,
    ]);

    SeasonDriver::create([
        'season_id' => $season->id,
        'driver_id' => $driver->id,
        'constructor_id' => $constructor->id,
        'number' => 1,
        'effective_from' => '2025-01-01',
    ]);

    $this->get(route('drivers.season', [$driver->slug, $season->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Drivers/Show')
            ->has('season')
            ->where('season.year', 2025)
            ->has('seasonStat')
            ->where('seasonStat.wins', 7)
            ->has('seasonDriver')
        );
});

test('season page shows all results for that season', function () {
    $driver = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);
    $season = Season::factory()->create(['franchise_id' => $this->franchise->id]);
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);
    $track = Track::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);

    DriverSeasonStat::factory()->create([
        'driver_id' => $driver->id,
        'season_id' => $season->id,
        'constructor_id' => $constructor->id,
    ]);

    for ($i = 0; $i < 5; $i++) {
        $event = Event::factory()->completed()->create([
            'season_id' => $season->id,
            'track_id' => $track->id,
        ]);

        EventResult::factory()->create([
            'event_id' => $event->id,
            'driver_id' => $driver->id,
            'constructor_id' => $constructor->id,
        ]);
    }

    $this->get(route('drivers.season', [$driver->slug, $season->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('eventResults', 5));
});

test('season page 404s when driver has no stats for that season', function () {
    $driver = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);
    $season = Season::factory()->create(['franchise_id' => $this->franchise->id]);

    $this->get(route('drivers.season', [$driver->slug, $season->id]))
        ->assertNotFound();
});

test('drivers index page renders with correct component', function () {
    Driver::factory()->count(3)->create([
        'franchise_id' => $this->franchise->id,
        'country_id' => $this->country->id,
        'is_active' => true,
    ]);

    $this->get(route('drivers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Drivers/Index')
            ->has('drivers.data', 3)
            ->has('franchises')
            ->has('filters')
        );
});

test('drivers index only shows active drivers', function () {
    Driver::factory()->create([
        'franchise_id' => $this->franchise->id,
        'country_id' => $this->country->id,
        'is_active' => true,
    ]);

    Driver::factory()->create([
        'franchise_id' => $this->franchise->id,
        'country_id' => $this->country->id,
        'is_active' => false,
    ]);

    $this->get(route('drivers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('drivers.data', 1)
        );
});

test('drivers index filters by franchise', function () {
    $otherFranchise = Franchise::factory()->create();

    Driver::factory()->create([
        'franchise_id' => $this->franchise->id,
        'country_id' => $this->country->id,
        'is_active' => true,
    ]);

    Driver::factory()->create([
        'franchise_id' => $otherFranchise->id,
        'country_id' => $this->country->id,
        'is_active' => true,
    ]);

    $this->get(route('drivers.index', ['franchise' => $this->franchise->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('drivers.data', 1)
            ->where('filters.franchise', $this->franchise->slug)
        );
});

test('driver show returns null currentSeasonDriver when no active season', function () {
    $driver = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);

    $this->get(route('drivers.show', $driver->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Drivers/Show')
            ->where('currentSeasonDriver', null)
        );
});
