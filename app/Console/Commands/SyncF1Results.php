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
use App\Services\OpenF1;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncF1Results extends Command
{
    protected $signature = 'f1:sync-results
        {--season= : Season year (defaults to current year)}
        {--round= : Only sync events for this round number}
        {--type= : Only sync events of this type (qualifying, race, sprint, sprint_qualifying)}
        {--source=openf1 : Data source to use (jolpica or openf1)}
        {--force : Re-sync events even if already completed}';

    protected $description = 'Sync F1 event results from Jolpica or OpenF1';

    protected F1DataService $f1;

    protected string $source;

    public function handle(Jolpica $jolpica, OpenF1 $openF1, F1DataService $f1): int
    {
        $this->f1 = $f1;
        $this->source = $this->option('source');

        if (! in_array($this->source, ['jolpica', 'openf1'])) {
            $this->error("Invalid source: {$this->source}. Use 'jolpica' or 'openf1'.");

            return Command::FAILURE;
        }

        $events = $this->getEventsToSync();

        if ($events->isEmpty()) {
            $this->info('No events to sync.');

            return Command::SUCCESS;
        }

        $this->info("Syncing {$events->count()} event(s) from {$this->source}...");

        foreach ($events as $event) {
            if ($this->source === 'openf1') {
                $this->syncEventFromOpenF1($event, $openF1);
            } else {
                $this->syncEventFromJolpica($event, $jolpica);
            }
        }

        return Command::SUCCESS;
    }

    protected function getEventsToSync(): Collection
    {
        $seasonYear = $this->option('season') ?: (string) now()->year;
        $season = Season::where('year', $seasonYear)->first();

        if (! $season) {
            $this->error("Season {$seasonYear} not found.");

            return collect();
        }

        $query = Event::with(['season.franchise', 'track'])
            ->where('season_id', $season->id)
            ->whereNotNull('round')
            ->whereIn('type', ['race', 'qualifying', 'sprint', 'sprint_qualifying']);

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        if ($round = $this->option('round')) {
            $query->where('round', (int) $round);
        }

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->where('status', '!=', 'completed')
                    ->orWhereDoesntHave('results');
            });
        }

        return $query->orderBy('sort_order')->get();
    }

    // ──────────────────────────────────────────────────────
    // Jolpica sync methods
    // ──────────────────────────────────────────────────────

    protected function syncEventFromJolpica(Event $event, Jolpica $jolpica): void
    {
        $year = $event->season->year;
        $round = $event->round;

        $this->info("Syncing: {$event->name} (Round {$round})");

        $driverMap = SeasonDriver::where('season_id', $event->season_id)
            ->whereNotNull('number')
            ->with('driver')
            ->get()
            ->keyBy('number')
            ->map(fn ($sd) => $sd->driver);

        match ($event->type) {
            'race' => $this->jolpicaSyncRace($event, $jolpica, $year, $round, $driverMap),
            'qualifying' => $this->jolpicaSyncQualifying($event, $jolpica, $year, $round, $driverMap),
            'sprint' => $this->jolpicaSyncSprint($event, $jolpica, $year, $round, $driverMap),
            'sprint_qualifying' => $this->jolpicaSyncSprintQualifying($event, $jolpica, $year, $round, $driverMap),
            default => null,
        };

        if ($event->results()->exists()) {
            $event->update(['status' => 'completed', 'last_synced_at' => now()]);
        }
    }

    protected function jolpicaSyncRace(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverMap): void
    {
        $results = $jolpica->getRaceResults($year, $round);

        if ($results->isEmpty()) {
            $this->warn("  No race results for round {$round}");

            return;
        }

        foreach ($results as $result) {
            $driverNumber = (int) $result['number'];
            $constructor = $this->resolveJolpicaConstructor($result);

            if (! $constructor) {
                continue;
            }

            $driver = $this->resolveJolpicaDriver($driverNumber, $result, $event, $constructor, $driverMap);
            $hasFastestLap = ($result['FastestLap']['rank'] ?? null) === '1';

            EventResult::updateOrCreate(
                ['event_id' => $event->id, 'driver_id' => $driver->id],
                [
                    'constructor_id' => $constructor->id,
                    'finish_position' => (int) $result['position'],
                    'grid_position' => ($result['grid'] ?? 0) ?: null,
                    'status' => $this->resolveJolpicaStatus($result['status']),
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

        $driverIdMap = $results->mapWithKeys(function ($result) use ($driverMap) {
            return [$result['Driver']['driverId'] => $driverMap[(int) $result['number']] ?? null];
        })->filter();

        $this->jolpicaSyncPitStops($event, $jolpica, $year, $round, $driverIdMap);
    }

    protected function jolpicaSyncQualifying(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverMap): void
    {
        $results = $jolpica->getQualifyingResults($year, $round);

        if ($results->isEmpty()) {
            $this->warn("  No qualifying results for round {$round}");

            return;
        }

        foreach ($results as $result) {
            $constructor = $this->resolveJolpicaConstructor($result);

            if (! $constructor) {
                continue;
            }

            $driver = $this->resolveJolpicaDriver((int) $result['number'], $result, $event, $constructor, $driverMap);

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

    protected function jolpicaSyncSprint(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverMap): void
    {
        $results = $jolpica->getSprintResults($year, $round);

        if ($results->isEmpty()) {
            $this->warn("  No sprint results for round {$round}");

            return;
        }

        foreach ($results as $result) {
            $constructor = $this->resolveJolpicaConstructor($result);

            if (! $constructor) {
                continue;
            }

            $driver = $this->resolveJolpicaDriver((int) $result['number'], $result, $event, $constructor, $driverMap);

            EventResult::updateOrCreate(
                ['event_id' => $event->id, 'driver_id' => $driver->id],
                [
                    'constructor_id' => $constructor->id,
                    'finish_position' => (int) $result['position'],
                    'grid_position' => ($result['grid'] ?? 0) ?: null,
                    'status' => $this->resolveJolpicaStatus($result['status']),
                    'fastest_lap' => false,
                    'driver_of_the_day' => false,
                    'data_source' => 'jolpica',
                ],
            );
        }

        $count = EventResult::where('event_id', $event->id)->count();
        $this->line("  Synced {$count} sprint results");
    }

    protected function jolpicaSyncSprintQualifying(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverMap): void
    {
        $results = $jolpica->getSprintQualifyingResults($year, $round);

        if ($results->isEmpty()) {
            $this->warn("  No sprint qualifying results for round {$round}");

            return;
        }

        foreach ($results as $result) {
            $constructor = $this->resolveJolpicaConstructor($result);

            if (! $constructor) {
                continue;
            }

            $driver = $this->resolveJolpicaDriver((int) $result['number'], $result, $event, $constructor, $driverMap);

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

    protected function jolpicaSyncPitStops(Event $event, Jolpica $jolpica, int $year, int $round, Collection $driverIdMap): void
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

    protected function resolveJolpicaDriver(int $number, array $result, Event $event, Constructor $constructor, Collection $driverMap): Driver
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

    protected function resolveJolpicaConstructor(array $result): ?Constructor
    {
        $constructorId = $result['Constructor']['constructorId'];
        $constructor = Constructor::where('jolpica_constructor_id', $constructorId)->first();

        if (! $constructor) {
            $this->warn("  Constructor not found: {$constructorId}");
        }

        return $constructor;
    }

    protected function resolveJolpicaStatus(string $jolpicaStatus): string
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

    // ──────────────────────────────────────────────────────
    // OpenF1 sync methods
    // ──────────────────────────────────────────────────────

    protected function syncEventFromOpenF1(Event $event, OpenF1 $openF1): void
    {
        $this->info("Syncing: {$event->name} ({$event->type}, Round {$event->round})");

        if (! $event->openf1_session_key) {
            $sessionKey = $this->discoverSessionKey($event, $openF1);

            if (! $sessionKey) {
                $this->warn('  Could not discover session key from OpenF1.');

                return;
            }

            $event->update(['openf1_session_key' => $sessionKey]);
            $this->line("  Discovered session key: {$sessionKey}");
        }

        $driverMap = SeasonDriver::where('season_id', $event->season_id)
            ->whereNotNull('number')
            ->with(['driver', 'constructor'])
            ->get()
            ->keyBy('number');

        match ($event->type) {
            'qualifying', 'sprint_qualifying' => $this->openF1SyncQualifying($event, $openF1, $driverMap),
            'race', 'sprint' => $this->openF1SyncRaceOrSprint($event, $openF1, $driverMap),
            default => null,
        };

        if ($event->results()->exists()) {
            $event->update(['status' => 'completed', 'last_synced_at' => now()]);
        }
    }

    protected function discoverSessionKey(Event $event, OpenF1 $openF1): ?int
    {
        $year = $event->season->year;
        $track = $event->track;

        $sessionTypeMap = [
            'qualifying' => 'Qualifying',
            'sprint_qualifying' => 'Sprint Qualifying',
            'sprint' => 'Sprint',
            'race' => 'Race',
        ];

        $sessionName = $sessionTypeMap[$event->type] ?? null;

        if (! $sessionName) {
            return null;
        }

        $sessions = $openF1->getSessions($year);

        $eventDate = $event->scheduled_at->format('Y-m-d');

        $match = $sessions->first(function ($session) use ($eventDate, $sessionName) {
            $sessionDate = substr($session['date_start'] ?? '', 0, 10);

            return $sessionDate === $eventDate && $session['session_name'] === $sessionName;
        });

        if (! $match && $track) {
            $match = $sessions->first(function ($session) use ($track, $sessionName) {
                $locationMatch = stripos($session['location'] ?? '', $track->location ?? '') !== false
                    || stripos($session['circuit_short_name'] ?? '', $track->name ?? '') !== false;

                return $locationMatch && $session['session_name'] === $sessionName;
            });
        }

        return $match['session_key'] ?? null;
    }

    protected function openF1SyncQualifying(Event $event, OpenF1 $openF1, Collection $driverMap): void
    {
        $sessionKey = $event->openf1_session_key;
        $results = $openF1->getSessionResults($sessionKey);

        if ($results->isEmpty()) {
            $this->warn('  No session results from OpenF1.');

            return;
        }

        $openF1Drivers = $openF1->getDrivers($sessionKey)->keyBy('driver_number');

        foreach ($results as $result) {
            $driverNumber = (int) $result['driver_number'];
            $seasonDriver = $driverMap[$driverNumber] ?? null;

            if (! $seasonDriver) {
                $openF1Driver = $openF1Drivers[$driverNumber] ?? null;
                $driverName = $openF1Driver['full_name'] ?? "Driver #{$driverNumber}";
                $this->warn("  Driver not in season: {$driverName} (#{$driverNumber}) — skipping.");

                continue;
            }

            $driver = $seasonDriver->driver;
            $constructor = $seasonDriver->constructor;

            $position = (int) $result['position'];
            $bestTime = isset($result['duration']) ? $this->secondsToTime((float) $result['duration']) : null;

            $isClassified = ! $result['dnf'] && ! $result['dns'] && ! $result['dsq'];
            $q1Time = $isClassified ? $bestTime : null;
            $q2Time = ($isClassified && $position <= 15) ? $bestTime : null;
            $q3Time = ($isClassified && $position <= 10) ? $bestTime : null;

            EventResult::updateOrCreate(
                ['event_id' => $event->id, 'driver_id' => $driver->id],
                [
                    'constructor_id' => $constructor->id,
                    'finish_position' => $position,
                    'grid_position' => $position,
                    'status' => $result['dnf'] ? 'dnf' : ($result['dns'] ? 'dns' : ($result['dsq'] ? 'dsq' : 'classified')),
                    'q1_time' => $q1Time,
                    'q2_time' => $q2Time,
                    'q3_time' => $q3Time,
                    'fastest_lap' => false,
                    'driver_of_the_day' => false,
                    'data_source' => 'openf1',
                ],
            );
        }

        $count = EventResult::where('event_id', $event->id)->count();
        $this->line("  Synced {$count} qualifying results.");

        $this->computeTeammateOutqualified($event);
    }

    protected function openF1SyncRaceOrSprint(Event $event, OpenF1 $openF1, Collection $driverMap): void
    {
        $sessionKey = $event->openf1_session_key;
        $results = $openF1->getSessionResults($sessionKey);

        if ($results->isEmpty()) {
            $this->warn('  No session results from OpenF1.');

            return;
        }

        $openF1Drivers = $openF1->getDrivers($sessionKey)->keyBy('driver_number');

        foreach ($results as $result) {
            $driverNumber = (int) $result['driver_number'];
            $seasonDriver = $driverMap[$driverNumber] ?? null;

            if (! $seasonDriver) {
                $openF1Driver = $openF1Drivers[$driverNumber] ?? null;
                $driverName = $openF1Driver['full_name'] ?? "Driver #{$driverNumber}";
                $this->warn("  Driver not in season: {$driverName} (#{$driverNumber}) — skipping.");

                continue;
            }

            $driver = $seasonDriver->driver;
            $constructor = $seasonDriver->constructor;

            EventResult::updateOrCreate(
                ['event_id' => $event->id, 'driver_id' => $driver->id],
                [
                    'constructor_id' => $constructor->id,
                    'finish_position' => (int) $result['position'],
                    'grid_position' => null,
                    'status' => $result['dnf'] ? 'dnf' : ($result['dns'] ? 'dns' : ($result['dsq'] ? 'dsq' : 'classified')),
                    'fastest_lap' => false,
                    'driver_of_the_day' => false,
                    'data_source' => 'openf1',
                ],
            );
        }

        $startingGrid = $openF1->getStartingGrid($sessionKey);

        foreach ($startingGrid as $gridEntry) {
            $driverNumber = (int) $gridEntry['driver_number'];
            $seasonDriver = $driverMap[$driverNumber] ?? null;

            if (! $seasonDriver) {
                continue;
            }

            EventResult::where('event_id', $event->id)
                ->where('driver_id', $seasonDriver->driver_id)
                ->update(['grid_position' => (int) $gridEntry['position']]);
        }

        $count = EventResult::where('event_id', $event->id)->count();
        $this->line("  Synced {$count} results.");

        if ($event->type === 'race') {
            $this->openF1SyncPitStops($event, $openF1, $driverMap);
        }
    }

    protected function openF1SyncPitStops(Event $event, OpenF1 $openF1, Collection $driverMap): void
    {
        $pitStops = $openF1->getPitStops($event->openf1_session_key);

        if ($pitStops->isEmpty()) {
            $this->warn('  No pit stop data.');

            return;
        }

        foreach ($pitStops as $pit) {
            $driverNumber = (int) $pit['driver_number'];
            $seasonDriver = $driverMap[$driverNumber] ?? null;

            if (! $seasonDriver) {
                continue;
            }

            $stopTime = $pit['stop_duration'] ?? $pit['lane_duration'] ?? null;

            if ($stopTime === null || $stopTime >= 999) {
                continue;
            }

            $result = EventResult::where('event_id', $event->id)
                ->where('driver_id', $seasonDriver->driver_id)
                ->first();

            if (! $result) {
                continue;
            }

            EventPitstop::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'driver_id' => $seasonDriver->driver_id,
                    'stop_lap' => (int) ($pit['lap_number'] ?? 0),
                ],
                [
                    'constructor_id' => $seasonDriver->constructor_id,
                    'stop_time_seconds' => (float) $stopTime,
                    'is_fastest_of_event' => false,
                    'data_source' => 'openf1',
                ],
            );
        }

        $this->markFastestPitStop($event);
    }

    // ──────────────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────────────

    protected function markFastestPitStop(Event $event): void
    {
        EventPitstop::where('event_id', $event->id)->update(['is_fastest_of_event' => false]);

        $fastest = EventPitstop::where('event_id', $event->id)->orderBy('stop_time_seconds')->first();

        if ($fastest) {
            $fastest->update(['is_fastest_of_event' => true]);
        }
    }

    protected function computeTeammateOutqualified(Event $event): void
    {
        $results = EventResult::where('event_id', $event->id)->get();

        $seasonDrivers = SeasonDriver::where('season_id', $event->season_id)
            ->whereNotNull('number')
            ->get();

        $byConstructor = $seasonDrivers->groupBy('constructor_id');

        foreach ($byConstructor as $teammates) {
            if ($teammates->count() < 2) {
                continue;
            }

            $teammateResults = $teammates->map(function ($sd) use ($results) {
                return $results->firstWhere('driver_id', $sd->driver_id);
            })->filter();

            if ($teammateResults->count() < 2) {
                continue;
            }

            $sorted = $teammateResults->sortBy('finish_position');
            $best = $sorted->first();

            foreach ($teammateResults as $result) {
                $result->update(['teammate_outqualified' => $result->id === $best->id]);
            }
        }

        $this->line('  Computed teammate outqualified flags.');
    }

    protected function secondsToTime(float $seconds): string
    {
        $minutes = (int) floor($seconds / 60);
        $remaining = $seconds - ($minutes * 60);

        return sprintf('00:%02d:%06.3f', $minutes, $remaining);
    }
}
