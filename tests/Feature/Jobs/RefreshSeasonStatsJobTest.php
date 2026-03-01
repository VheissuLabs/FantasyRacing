<?php

use App\Jobs\RefreshSeasonStats;
use App\Models\Constructor;
use App\Models\Driver;
use App\Models\DriverSeasonStat;
use App\Models\Event;
use App\Models\EventResult;
use App\Models\Franchise;
use App\Models\Season;
use App\Models\SeasonConstructor;
use App\Models\SeasonDriver;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->franchise = Franchise::factory()->create();
    $this->season = Season::factory()->create(['franchise_id' => $this->franchise->id]);
    $this->track = Track::create(['franchise_id' => $this->franchise->id, 'name' => 'Spa', 'location' => 'Spa-Francorchamps', 'country' => 'Belgium']);

    $this->constructor = Constructor::create(['franchise_id' => $this->franchise->id, 'name' => 'McLaren', 'slug' => 'mclaren', 'is_active' => true]);
    $this->driver = Driver::create(['franchise_id' => $this->franchise->id, 'name' => 'Lando Norris', 'slug' => 'lando-norris', 'is_active' => true]);

    SeasonConstructor::create(['season_id' => $this->season->id, 'constructor_id' => $this->constructor->id]);
    SeasonDriver::create([
        'season_id' => $this->season->id,
        'driver_id' => $this->driver->id,
        'constructor_id' => $this->constructor->id,
        'number' => 4,
        'effective_from' => now()->startOfYear(),
    ]);
});

test('job computes driver season stats from event results', function () {
    $raceEvent = Event::create([
        'season_id' => $this->season->id,
        'track_id' => $this->track->id,
        'name' => 'Belgian GP Race',
        'type' => 'race',
        'status' => 'completed',
        'scheduled_at' => now()->subDays(7),
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $raceEvent->id,
        'driver_id' => $this->driver->id,
        'constructor_id' => $this->constructor->id,
        'finish_position' => 1,
        'grid_position' => 3,
        'status' => 'classified',
        'fastest_lap' => true,
        'driver_of_the_day' => false,
        'overtakes_made' => 5,
        'points_eligible' => true,
        'data_source' => 'manual',
    ]);

    (new RefreshSeasonStats($this->season))->handle();

    $stat = DriverSeasonStat::where('driver_id', $this->driver->id)
        ->where('season_id', $this->season->id)
        ->first();

    expect($stat)->not->toBeNull()
        ->and($stat->races_entered)->toBe(1)
        ->and($stat->races_classified)->toBe(1)
        ->and($stat->wins)->toBe(1)
        ->and($stat->podiums)->toBe(1)
        ->and($stat->fastest_laps)->toBe(1)
        ->and($stat->dnfs)->toBe(0)
        ->and($stat->best_finish)->toBe(1)
        ->and($stat->championship_position)->toBe(1)
        ->and($stat->constructor_id)->toBe($this->constructor->id)
        ->and($stat->last_computed_at)->not->toBeNull();
});

test('job computes DNF stats correctly', function () {
    $raceEvent = Event::create([
        'season_id' => $this->season->id,
        'track_id' => $this->track->id,
        'name' => 'Belgian GP Race',
        'type' => 'race',
        'status' => 'completed',
        'scheduled_at' => now()->subDays(7),
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $raceEvent->id,
        'driver_id' => $this->driver->id,
        'constructor_id' => $this->constructor->id,
        'finish_position' => null,
        'grid_position' => 5,
        'status' => 'dnf',
        'fastest_lap' => false,
        'driver_of_the_day' => false,
        'overtakes_made' => 0,
        'points_eligible' => false,
        'data_source' => 'manual',
    ]);

    (new RefreshSeasonStats($this->season))->handle();

    $stat = DriverSeasonStat::where('driver_id', $this->driver->id)->first();

    expect($stat->races_entered)->toBe(1)
        ->and($stat->races_classified)->toBe(0)
        ->and($stat->wins)->toBe(0)
        ->and($stat->dnfs)->toBe(1)
        ->and($stat->best_finish)->toBeNull();
});

test('job does nothing when no completed events exist', function () {
    (new RefreshSeasonStats($this->season))->handle();

    expect(DriverSeasonStat::count())->toBe(0);
});

test('job computes qualifying poles', function () {
    $qualifyingEvent = Event::create([
        'season_id' => $this->season->id,
        'track_id' => $this->track->id,
        'name' => 'Belgian GP Qualifying',
        'type' => 'qualifying',
        'status' => 'completed',
        'scheduled_at' => now()->subDays(8),
        'sort_order' => 1,
        'round' => 1,
    ]);

    EventResult::create([
        'event_id' => $qualifyingEvent->id,
        'driver_id' => $this->driver->id,
        'constructor_id' => $this->constructor->id,
        'finish_position' => 1,
        'grid_position' => null,
        'status' => 'classified',
        'fastest_lap' => false,
        'driver_of_the_day' => false,
        'overtakes_made' => 0,
        'points_eligible' => true,
        'data_source' => 'manual',
    ]);

    (new RefreshSeasonStats($this->season))->handle();

    $stat = DriverSeasonStat::where('driver_id', $this->driver->id)->first();

    expect($stat->poles)->toBe(1)
        ->and($stat->races_entered)->toBe(0);
});
