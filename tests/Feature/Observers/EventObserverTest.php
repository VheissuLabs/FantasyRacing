<?php

use App\Models\Constructor;
use App\Models\Driver;
use App\Models\Event;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamRoster;
use App\Models\Franchise;
use App\Models\League;
use App\Models\RosterSnapshot;
use App\Models\Season;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->franchise = Franchise::factory()->create();
    $this->season = Season::factory()->create(['franchise_id' => $this->franchise->id]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'franchise_id' => $this->franchise->id]);

    $this->track = Track::create(['franchise_id' => $this->franchise->id, 'name' => 'Silverstone', 'location' => 'Silverstone', 'country' => 'UK']);
    $this->event = Event::create([
        'season_id' => $this->season->id,
        'track_id' => $this->track->id,
        'name' => 'British GP Race',
        'type' => 'race',
        'status' => 'scheduled',
        'scheduled_at' => now()->addDays(7),
        'sort_order' => 1,
        'round' => 1,
    ]);

    $this->driver = Driver::create(['franchise_id' => $this->franchise->id, 'name' => 'Max Verstappen', 'slug' => 'max-verstappen', 'is_active' => true]);
    $this->constructor = Constructor::create(['franchise_id' => $this->franchise->id, 'name' => 'Red Bull Racing', 'slug' => 'red-bull-racing', 'is_active' => true]);

    $this->team = FantasyTeam::factory()->create(['league_id' => $this->league->id]);
    FantasyTeamRoster::create(['fantasy_team_id' => $this->team->id, 'entity_type' => 'driver', 'entity_id' => $this->driver->id, 'in_seat' => true, 'acquired_at' => now()]);
    FantasyTeamRoster::create(['fantasy_team_id' => $this->team->id, 'entity_type' => 'constructor', 'entity_id' => $this->constructor->id, 'in_seat' => true, 'acquired_at' => now()]);
});

test('roster snapshots are created when event status changes to locked', function () {
    $this->event->update(['status' => 'locked', 'locked_at' => now()]);

    expect(RosterSnapshot::count())->toBe(1);

    $snapshot = RosterSnapshot::first();
    expect($snapshot->event_id)->toBe($this->event->id)
        ->and($snapshot->fantasy_team_id)->toBe($this->team->id)
        ->and($snapshot->snapshot)->toHaveCount(2);

    $driverEntry = collect($snapshot->snapshot)->firstWhere('entity_type', 'driver');
    expect($driverEntry['entity_id'])->toBe($this->driver->id)
        ->and($driverEntry['in_seat'])->toBeTrue();

    $constructorEntry = collect($snapshot->snapshot)->firstWhere('entity_type', 'constructor');
    expect($constructorEntry['entity_id'])->toBe($this->constructor->id);
});

test('no snapshots are created for non-locked status changes', function () {
    $this->event->update(['status' => 'in_progress']);

    expect(RosterSnapshot::count())->toBe(0);
});

test('snapshots are updated if event is re-locked', function () {
    $this->event->update(['status' => 'locked', 'locked_at' => now()]);
    expect(RosterSnapshot::count())->toBe(1);

    $secondDriver = Driver::create(['franchise_id' => $this->franchise->id, 'name' => 'Sergio Perez', 'slug' => 'sergio-perez', 'is_active' => true]);
    FantasyTeamRoster::create(['fantasy_team_id' => $this->team->id, 'entity_type' => 'driver', 'entity_id' => $secondDriver->id, 'in_seat' => true, 'acquired_at' => now()]);

    $this->event->update(['status' => 'scheduled']);
    $this->event->update(['status' => 'locked', 'locked_at' => now()]);

    expect(RosterSnapshot::count())->toBe(1);
    $snapshot = RosterSnapshot::first();
    expect($snapshot->snapshot)->toHaveCount(3);
});

test('snapshots cover all teams in the season', function () {
    $secondTeam = FantasyTeam::factory()->create(['league_id' => $this->league->id]);
    $secondDriver = Driver::create(['franchise_id' => $this->franchise->id, 'name' => 'Lewis Hamilton', 'slug' => 'lewis-hamilton', 'is_active' => true]);
    FantasyTeamRoster::create(['fantasy_team_id' => $secondTeam->id, 'entity_type' => 'driver', 'entity_id' => $secondDriver->id, 'in_seat' => true, 'acquired_at' => now()]);

    $this->event->update(['status' => 'locked', 'locked_at' => now()]);

    expect(RosterSnapshot::count())->toBe(2);
    expect(RosterSnapshot::where('fantasy_team_id', $this->team->id)->exists())->toBeTrue();
    expect(RosterSnapshot::where('fantasy_team_id', $secondTeam->id)->exists())->toBeTrue();
});
