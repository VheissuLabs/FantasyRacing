<?php

use App\Jobs\CalculateEventPoints;
use App\Jobs\RefreshSeasonStats;
use App\Models\Constructor;
use App\Models\Driver;
use App\Models\Event;
use App\Models\EventResult;
use App\Models\FantasyEventPoint;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamRoster;
use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use App\Models\Track;
use App\Notifications\EventPointsCalculatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->franchise = Franchise::factory()->create();
    $this->season = Season::factory()->create(['franchise_id' => $this->franchise->id]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'franchise_id' => $this->franchise->id]);

    $this->track = Track::create(['franchise_id' => $this->franchise->id, 'name' => 'Monza', 'location' => 'Monza', 'country' => 'Italy']);
    $this->event = Event::create([
        'season_id' => $this->season->id,
        'track_id' => $this->track->id,
        'name' => 'Italian GP Race',
        'type' => 'race',
        'status' => 'completed',
        'scheduled_at' => now()->subDay(),
        'sort_order' => 1,
        'round' => 1,
    ]);

    $this->driver = Driver::create(['franchise_id' => $this->franchise->id, 'name' => 'Max Verstappen', 'slug' => 'max-verstappen', 'is_active' => true]);
    $this->constructor = Constructor::create(['franchise_id' => $this->franchise->id, 'name' => 'Red Bull Racing', 'slug' => 'red-bull-racing', 'is_active' => true]);

    $this->team = FantasyTeam::factory()->create(['league_id' => $this->league->id]);
    FantasyTeamRoster::create(['fantasy_team_id' => $this->team->id, 'entity_type' => 'driver', 'entity_id' => $this->driver->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $this->team->id, 'entity_type' => 'constructor', 'entity_id' => $this->constructor->id, 'in_seat' => true, 'acquired_at' => now()]);

    EventResult::create([
        'event_id' => $this->event->id,
        'driver_id' => $this->driver->id,
        'constructor_id' => $this->constructor->id,
        'finish_position' => 1,
        'grid_position' => 1,
        'status' => 'classified',
        'fastest_lap' => false,
        'driver_of_the_day' => false,
        'overtakes_made' => 0,
        'points_eligible' => true,
        'data_source' => 'manual',
    ]);
});

test('job calculates and persists fantasy event points', function () {
    Queue::fake([RefreshSeasonStats::class]);
    Notification::fake();

    (new CalculateEventPoints($this->event))->handle(app(\App\Services\PointsCalculationService::class));

    expect(FantasyEventPoint::count())->toBeGreaterThanOrEqual(1);

    $driverPoints = FantasyEventPoint::where('fantasy_team_id', $this->team->id)
        ->where('event_id', $this->event->id)
        ->where('entity_type', 'driver')
        ->where('entity_id', $this->driver->id)
        ->first();

    expect($driverPoints)->not->toBeNull()
        ->and($driverPoints->computed_at)->not->toBeNull();
});

test('job dispatches refresh season stats', function () {
    Queue::fake([RefreshSeasonStats::class]);
    Notification::fake();

    (new CalculateEventPoints($this->event))->handle(app(\App\Services\PointsCalculationService::class));

    Queue::assertPushed(RefreshSeasonStats::class, function ($job) {
        return $job->season->id === $this->season->id;
    });
});

test('job notifies team owners of their points', function () {
    Queue::fake([RefreshSeasonStats::class]);
    Notification::fake();

    (new CalculateEventPoints($this->event))->handle(app(\App\Services\PointsCalculationService::class));

    Notification::assertSentTo(
        $this->team->user,
        EventPointsCalculatedNotification::class,
        function ($notification) {
            return $notification->event->id === $this->event->id;
        }
    );
});

test('cli command dispatches job for a specific event', function () {
    Queue::fake([CalculateEventPoints::class]);

    $this->artisan('points:calculate', ['event' => $this->event->id])
        ->assertSuccessful();

    Queue::assertPushed(CalculateEventPoints::class, function ($job) {
        return $job->event->id === $this->event->id;
    });
});

test('cli command dispatches jobs for all pending events', function () {
    Queue::fake([CalculateEventPoints::class]);

    $this->artisan('points:calculate', ['--all-pending' => true])
        ->assertSuccessful();

    Queue::assertPushed(CalculateEventPoints::class);
});
