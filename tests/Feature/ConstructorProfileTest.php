<?php

use App\Models\Constructor;
use App\Models\ConstructorSeasonStat;
use App\Models\Country;
use App\Models\Driver;
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

test('constructor profile page renders', function () {
    Season::factory()->active()->create(['franchise_id' => $this->franchise->id]);
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);

    $this->get(route('constructors.show', $constructor->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Constructors/Show')
            ->has('constructor')
            ->has('careerSummary')
            ->has('availableSeasons')
        );
});

test('shows current driver lineup from active season', function () {
    $season = Season::factory()->active()->create(['franchise_id' => $this->franchise->id]);
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);

    $driver1 = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id, 'name' => 'Driver One']);
    $driver2 = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id, 'name' => 'Driver Two']);

    SeasonDriver::create([
        'season_id' => $season->id,
        'driver_id' => $driver1->id,
        'constructor_id' => $constructor->id,
        'number' => 1,
        'effective_from' => '2025-01-01',
    ]);

    SeasonDriver::create([
        'season_id' => $season->id,
        'driver_id' => $driver2->id,
        'constructor_id' => $constructor->id,
        'number' => 2,
        'effective_from' => '2025-01-01',
    ]);

    $this->get(route('constructors.show', $constructor->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('currentDrivers', 2)
            ->where('currentDrivers.0.name', 'Driver One')
            ->where('currentDrivers.1.name', 'Driver Two')
        );
});

test('career summary aggregates correctly', function () {
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);
    Season::factory()->active()->create(['franchise_id' => $this->franchise->id]);

    $season1 = Season::factory()->create(['franchise_id' => $this->franchise->id, 'year' => 2024]);
    $season2 = Season::factory()->create(['franchise_id' => $this->franchise->id, 'year' => 2025]);

    ConstructorSeasonStat::factory()->create([
        'constructor_id' => $constructor->id,
        'season_id' => $season1->id,
        'races_entered' => 20,
        'wins' => 5,
        'podiums' => 12,
        'one_twos' => 3,
        'poles' => 7,
        'championship_position' => 1,
    ]);

    ConstructorSeasonStat::factory()->create([
        'constructor_id' => $constructor->id,
        'season_id' => $season2->id,
        'races_entered' => 22,
        'wins' => 8,
        'podiums' => 15,
        'one_twos' => 5,
        'poles' => 9,
        'championship_position' => 2,
    ]);

    $this->get(route('constructors.show', $constructor->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('careerSummary.seasons', 2)
            ->where('careerSummary.races_entered', 42)
            ->where('careerSummary.wins', 13)
            ->where('careerSummary.podiums', 27)
            ->where('careerSummary.best_championship', 1)
        );
});

test('season page renders with grouped results', function () {
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);
    $season = Season::factory()->create(['franchise_id' => $this->franchise->id]);
    $track = Track::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);

    ConstructorSeasonStat::factory()->create([
        'constructor_id' => $constructor->id,
        'season_id' => $season->id,
    ]);

    $driver1 = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);
    $driver2 = Driver::factory()->create(['franchise_id' => $this->franchise->id, 'country_id' => $this->country->id]);

    $event = Event::factory()->completed()->create([
        'season_id' => $season->id,
        'track_id' => $track->id,
    ]);

    EventResult::factory()->create([
        'event_id' => $event->id,
        'driver_id' => $driver1->id,
        'constructor_id' => $constructor->id,
        'finish_position' => 1,
    ]);

    EventResult::factory()->create([
        'event_id' => $event->id,
        'driver_id' => $driver2->id,
        'constructor_id' => $constructor->id,
        'finish_position' => 2,
    ]);

    $this->get(route('constructors.season', [$constructor->slug, $season->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Constructors/Show')
            ->has('season')
            ->has('seasonStat')
            ->has('eventResults', 1)
            ->has('eventResults.0.results', 2)
        );
});

test('season page 404s for invalid season', function () {
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);
    $season = Season::factory()->create(['franchise_id' => $this->franchise->id]);

    $this->get(route('constructors.season', [$constructor->slug, $season->id]))
        ->assertNotFound();
});

test('constructor profile shows season history', function () {
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);
    Season::factory()->active()->create(['franchise_id' => $this->franchise->id]);

    $season = Season::factory()->create(['franchise_id' => $this->franchise->id, 'year' => 2025, 'name' => '2025']);

    ConstructorSeasonStat::factory()->create([
        'constructor_id' => $constructor->id,
        'season_id' => $season->id,
    ]);

    $this->get(route('constructors.show', $constructor->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('seasonStats', 1)
            ->where('seasonStats.0.season.name', '2025')
        );
});

test('constructors index page renders with correct component', function () {
    Constructor::factory()->count(3)->create([
        'franchise_id' => $this->franchise->id,
        'is_active' => true,
    ]);

    $this->get(route('constructors.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Constructors/Index')
            ->has('constructors.data', 3)
        );
});

test('constructors index only shows active constructors', function () {
    Constructor::factory()->create([
        'franchise_id' => $this->franchise->id,
        'is_active' => true,
    ]);

    Constructor::factory()->create([
        'franchise_id' => $this->franchise->id,
        'is_active' => false,
    ]);

    $this->get(route('constructors.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('constructors.data', 1)
        );
});

test('constructors index filters by franchise via cookie', function () {
    $otherFranchise = Franchise::factory()->create();

    Constructor::factory()->create([
        'franchise_id' => $this->franchise->id,
        'is_active' => true,
    ]);

    Constructor::factory()->create([
        'franchise_id' => $otherFranchise->id,
        'is_active' => true,
    ]);

    $this->withUnencryptedCookie('franchise', $this->franchise->slug)
        ->get(route('constructors.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('constructors.data', 1)
        );
});

test('constructor show returns empty currentDrivers when no active season', function () {
    $constructor = Constructor::factory()->create(['franchise_id' => $this->franchise->id]);

    $this->get(route('constructors.show', $constructor->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Constructors/Show')
            ->where('currentDrivers', [])
        );
});
