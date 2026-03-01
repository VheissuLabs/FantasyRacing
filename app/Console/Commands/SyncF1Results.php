<?php

namespace App\Console\Commands;

use App\Models\Constructor;
use App\Models\Driver;
use App\Models\Event;
use App\Models\EventPitstop;
use App\Models\EventResult;
use App\Models\Season;
use App\Models\SeasonDriver;
use App\Services\F1DataService;
use App\Services\Jolpica;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncF1Results extends Command
{
    protected $signature = 'f1:sync-results
        {--season= : Only sync events for this season year (e.g. 2024)}
        {--round= : Only sync events for this round number}
        {--force : Re-sync events even if already completed}';

    protected $description = 'Sync F1 event results from Jolpica';

    protected F1DataService $f1;

    public function handle(Jolpica $jolpica, F1DataService $f1): int
    {
        $this->f1 = $f1;

        $events = $this->getEventsToSync();

        if ($events->isEmpty()) {
            $this->info('No events to sync.');

            return Command::SUCCESS;
        }

        $this->info("Syncing {$events->count()} events...");

        foreach ($events as $event) {
            $this->syncEvent($event, $jolpica);
        }

        return Command::SUCCESS;
    }

    protected function getEventsToSync(): Collection
    {
        $query = Event::with(['season.franchise'])
            ->whereNotNull('round')
            ->whereIn('type', ['race', 'qualifying', 'sprint', 'sprint_qualifying']);

        if ($seasonYear = $this->option('season')) {
            $season = Season::where('year', $seasonYear)->first();

            if (! $season) {
                $this->error("Season {$seasonYear} not found.");

                return collect();
            }

            $query->where('season_id', $season->id);
        }

        if ($round = $this->option('round')) {
            $query->where('round', (int) $round);
        }

        if (! $this->option('force')) {
            $query->where('status', '!=', 'completed');
        }

        return $query->orderBy('sort_order')->get();
    }

    protected function syncEvent(Event $event, Jolpica $jolpica): void
    {
        $year = $event->season->year;
        $round = $event->round;

        $this->info("Syncing: {$event->name} (Round {$round})");

        // Build driver map: car_number => Driver for this season.
        // This may be updated on-the-fly if mid-season substitutes are encountered.
        $driverMap = SeasonDriver::where('season_id', $event->season_id)
            ->whereNotNull('number')
            ->with('driver')
            ->get()
            ->keyBy('number')
            ->map(fn ($sd) => $sd->driver);

        match ($event->type) {
            'race' => $this->syncRace($event, $jolpica, $year, $round, $driverMap),
            'qualifying' => $this->syncQualifying($event, $jolpica, $year, $round, $driverMap),
            'sprint' => $this->syncSprint($event, $jolpica, $year, $round, $driverMap),
            'sprint_qualifying' => $this->syncSprintQualifying($event, $jolpica, $year, $round, $driverMap),
            default => null,
        };

        $event->update(['status' => 'completed', 'last_synced_at' => now()]);
    }

    protected function syncRace(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverMap): void
    {
        $results = $jolpica->getRaceResults($year, $round);

        if ($results->isEmpty()) {
            $this->warn("  No race results for round {$round}");

            return;
        }

        foreach ($results as $result) {
            $driverNumber = (int) $result['number'];
            $constructor = $this->resolveConstructor($result);

            if (! $constructor) {
                continue;
            }

            $driver = $this->resolveDriver($driverNumber, $result, $event, $constructor, $driverMap);
            $hasFastestLap = ($result['FastestLap']['rank'] ?? null) === '1';

            EventResult::updateOrCreate(
                ['event_id' => $event->id, 'driver_id' => $driver->id],
                [
                    'constructor_id' => $constructor->id,
                    'finish_position' => (int) $result['position'],
                    'grid_position' => ($result['grid'] ?? 0) ?: null,
                    'status' => $this->resolveStatus($result['status']),
                    'fastest_lap' => $hasFastestLap,
                    'driver_of_the_day' => false,
                    'data_source' => 'jolpica',
                ],
            );

            if ($hasFastestLap) {
                $this->line("  Fastest lap: {$driver->name}");
            }
        }

        $count = EventResult::where('event_id', $event->id)->count();
        $this->line("  Synced {$count} results");

        // Build driverId => Driver map from the now-complete driverMap for pit stop lookups.
        $driverIdMap = $results->mapWithKeys(function ($result) use ($driverMap) {
            return [$result['Driver']['driverId'] => $driverMap[(int) $result['number']] ?? null];
        })->filter();

        $this->syncPitStops($event, $jolpica, $year, $round, $driverIdMap);
    }

    protected function syncQualifying(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverMap): void
    {
        $results = $jolpica->getQualifyingResults($year, $round);

        if ($results->isEmpty()) {
            $this->warn("  No qualifying results for round {$round}");

            return;
        }

        foreach ($results as $result) {
            $constructor = $this->resolveConstructor($result);

            if (! $constructor) {
                continue;
            }

            $driver = $this->resolveDriver((int) $result['number'], $result, $event, $constructor, $driverMap);

            EventResult::updateOrCreate(
                ['event_id' => $event->id, 'driver_id' => $driver->id],
                [
                    'constructor_id' => $constructor->id,
                    'finish_position' => (int) $result['position'],
                    'grid_position' => (int) $result['position'],
                    'status' => 'classified',
                    'fastest_lap' => false,
                    'driver_of_the_day' => false,
                    'data_source' => 'jolpica',
                ],
            );
        }

        $count = EventResult::where('event_id', $event->id)->count();
        $this->line("  Synced {$count} qualifying results");
    }

    protected function syncSprint(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverMap): void
    {
        $results = $jolpica->getSprintResults($year, $round);

        if ($results->isEmpty()) {
            $this->warn("  No sprint results for round {$round}");

            return;
        }

        foreach ($results as $result) {
            $constructor = $this->resolveConstructor($result);

            if (! $constructor) {
                continue;
            }

            $driver = $this->resolveDriver((int) $result['number'], $result, $event, $constructor, $driverMap);

            EventResult::updateOrCreate(
                ['event_id' => $event->id, 'driver_id' => $driver->id],
                [
                    'constructor_id' => $constructor->id,
                    'finish_position' => (int) $result['position'],
                    'grid_position' => ($result['grid'] ?? 0) ?: null,
                    'status' => $this->resolveStatus($result['status']),
                    'fastest_lap' => false,
                    'driver_of_the_day' => false,
                    'data_source' => 'jolpica',
                ],
            );
        }

        $count = EventResult::where('event_id', $event->id)->count();
        $this->line("  Synced {$count} sprint results");
    }

    protected function syncSprintQualifying(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverMap): void
    {
        $results = $jolpica->getSprintQualifyingResults($year, $round);

        if ($results->isEmpty()) {
            $this->warn("  No sprint qualifying results for round {$round}");

            return;
        }

        foreach ($results as $result) {
            $constructor = $this->resolveConstructor($result);

            if (! $constructor) {
                continue;
            }

            $driver = $this->resolveDriver((int) $result['number'], $result, $event, $constructor, $driverMap);

            EventResult::updateOrCreate(
                ['event_id' => $event->id, 'driver_id' => $driver->id],
                [
                    'constructor_id' => $constructor->id,
                    'finish_position' => (int) $result['position'],
                    'grid_position' => (int) $result['position'],
                    'status' => 'classified',
                    'fastest_lap' => false,
                    'driver_of_the_day' => false,
                    'data_source' => 'jolpica',
                ],
            );
        }

        $count = EventResult::where('event_id', $event->id)->count();
        $this->line("  Synced {$count} sprint qualifying results");
    }

    protected function syncPitStops(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverIdMap): void
    {
        $pitstops = $jolpica->getPitStops($year, $round);

        if ($pitstops->isEmpty()) {
            $this->warn("  No pit stop data for round {$round}");

            return;
        }

        foreach ($pitstops as $pit) {
            $driver = $driverIdMap[$pit['driverId']] ?? null;

            if (! $driver) {
                continue;
            }

            $stopTime = isset($pit['duration']) ? (float) $pit['duration'] : null;

            if ($stopTime === null || $stopTime >= 999) {
                continue;
            }

            $result = EventResult::where('event_id', $event->id)
                ->where('driver_id', $driver->id)
                ->first();

            if (! $result) {
                continue;
            }

            EventPitstop::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'driver_id' => $driver->id,
                    'stop_lap' => (int) ($pit['lap'] ?? 0),
                ],
                [
                    'constructor_id' => $result->constructor_id,
                    'stop_time_seconds' => $stopTime,
                    'is_fastest_of_event' => false,
                    'data_source' => 'jolpica',
                ],
            );
        }

        $this->markFastestPitStop($event);
    }

    protected function markFastestPitStop(Event $event): void
    {
        EventPitstop::where('event_id', $event->id)->update(['is_fastest_of_event' => false]);

        $fastest = EventPitstop::where('event_id', $event->id)->orderBy('stop_time_seconds')->first();

        if ($fastest) {
            $fastest->update(['is_fastest_of_event' => true]);
        }
    }

    /**
     * Find a driver by car number in the season driver map, auto-creating them if
     * they are a mid-season substitute not captured during initial seeding.
     */
    protected function resolveDriver(int $number, array $result, Event $event, Constructor $constructor, Collection $driverMap): Driver
    {
        if ($driver = ($driverMap[$number] ?? null)) {
            return $driver;
        }

        $driver = $this->f1->resolveDriver($result['Driver'], $event->season->franchise);

        SeasonDriver::firstOrCreate(
            [
                'season_id' => $event->season_id,
                'driver_id' => $driver->id,
                'number' => $number,
            ],
            [
                'constructor_id' => $constructor->id,
                'effective_from' => "{$event->season->year}-01-01",
            ],
        );

        $driverMap[$number] = $driver;

        $this->line("  → Added mid-season driver: {$driver->name} (#{$number})");

        return $driver;
    }

    protected function resolveConstructor(array $result): ?Constructor
    {
        $constructorId = $result['Constructor']['constructorId'];
        $constructor = Constructor::where('jolpica_constructor_id', $constructorId)->first();

        if (! $constructor) {
            $this->warn("  Constructor not found: {$constructorId}");
        }

        return $constructor;
    }

    protected function resolveStatus(string $jolpicaStatus): string
    {
        if ($jolpicaStatus === 'Finished' || str_starts_with($jolpicaStatus, '+')) {
            return 'classified';
        }

        if ($jolpicaStatus === 'Disqualified') {
            return 'dsq';
        }

        if (in_array($jolpicaStatus, ['Did Not Start', 'Withdrew', 'Not Classified'])) {
            return 'dns';
        }

        return 'dnf';
    }
}
