<?php

namespace App\Console\Commands;

use App\Jobs\CalculateEventPoints;
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
        {--force : Re-sync events even if already completed}';

    protected $description = 'Sync F1 event results from Jolpica, enriched with OpenF1 data';

    protected F1DataService $f1;

    public function handle(Jolpica $jolpica, OpenF1 $openF1, F1DataService $f1): int
    {
        $this->f1 = $f1;

        $events = $this->getEventsToSync();

        if ($events->isEmpty()) {
            $this->info('No events to sync.');

            return Command::SUCCESS;
        }

        $this->info("Syncing {$events->count()} event(s)...");

        foreach ($events as $event) {
            $this->syncEvent($event, $jolpica, $openF1);
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

        $query->where('scheduled_at', '<=', now());

        return $query->orderBy('sort_order')->get();
    }

    // ──────────────────────────────────────────────────────
    // Unified sync: Jolpica for results, OpenF1 to enrich
    // ──────────────────────────────────────────────────────

    protected function syncEvent(Event $event, Jolpica $jolpica, OpenF1 $openF1): void
    {
        $year = $event->season->year;
        $round = $event->round;

        $this->info("Syncing: {$event->name} ({$event->type}, Round {$round})");

        // Build driver map for this season
        $driverMap = SeasonDriver::where('season_id', $event->season_id)
            ->whereNotNull('number')
            ->with('driver')
            ->get()
            ->keyBy('number')
            ->map(fn ($sd) => $sd->driver);

        // 1. Sync results from Jolpica (primary source)
        match ($event->type) {
            'race' => $this->syncRace($event, $jolpica, $year, $round, $driverMap),
            'qualifying' => $this->syncQualifying($event, $jolpica, $year, $round, $driverMap),
            'sprint' => $this->syncSprint($event, $jolpica, $year, $round, $driverMap),
            'sprint_qualifying' => $this->syncSprintQualifying($event, $jolpica, $year, $round, $driverMap),
            default => null,
        };

        // 2. Enrich with OpenF1 data (photos, colours, Q-times, teammate flags)
        $this->enrichFromOpenF1($event, $openF1);

        // 3. Mark completed and calculate points
        if ($event->results()->exists()) {
            $event->update(['status' => 'completed', 'last_synced_at' => now()]);
            CalculateEventPoints::dispatch($event);
            $this->line("  Dispatched points calculation for {$event->name}.");
        }
    }

    // ──────────────────────────────────────────────────────
    // Jolpica: core results
    // ──────────────────────────────────────────────────────

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

            $q1Time = isset($result['Q1']) ? $this->lapTimeToTime($result['Q1']) : null;
            $q2Time = isset($result['Q2']) ? $this->lapTimeToTime($result['Q2']) : null;
            $q3Time = isset($result['Q3']) ? $this->lapTimeToTime($result['Q3']) : null;

            EventResult::updateOrCreate(
                ['event_id' => $event->id, 'driver_id' => $driver->id],
                [
                    'constructor_id' => $constructor->id,
                    'finish_position' => (int) $result['position'],
                    'grid_position' => (int) $result['position'],
                    'status' => 'classified',
                    'q1_time' => $q1Time,
                    'q2_time' => $q2Time,
                    'q3_time' => $q3Time,
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

    // ──────────────────────────────────────────────────────
    // OpenF1: enrichment (photos, colours, Q-times, flags)
    // ──────────────────────────────────────────────────────

    protected function enrichFromOpenF1(Event $event, OpenF1 $openF1): void
    {
        if (! $event->openf1_session_key) {
            $sessionKey = $this->discoverSessionKey($event, $openF1);

            if (! $sessionKey) {
                return;
            }

            $event->update(['openf1_session_key' => $sessionKey]);
            $this->line("  Discovered OpenF1 session key: {$sessionKey}");
        }

        $sessionKey = $event->openf1_session_key;

        $seasonDriverMap = SeasonDriver::where('season_id', $event->season_id)
            ->whereNotNull('number')
            ->with(['driver', 'constructor'])
            ->get()
            ->keyBy('number');

        $openF1Drivers = $openF1->getDrivers($sessionKey)->keyBy('driver_number');

        // Sync driver photos and constructor colours
        $this->syncDriverPhotos($seasonDriverMap, $openF1Drivers);
        $this->syncConstructorColours($seasonDriverMap, $openF1Drivers);

        // Enrich qualifying with precise Q-times from OpenF1
        if (in_array($event->type, ['qualifying', 'sprint_qualifying'])) {
            $this->enrichQualifyingTimes($event, $openF1, $seasonDriverMap, $openF1Drivers);
            $this->computeTeammateOutqualified($event);
        }
    }

    protected function enrichQualifyingTimes(Event $event, OpenF1 $openF1, Collection $seasonDriverMap, Collection $openF1Drivers): void
    {
        $results = $openF1->getSessionResults($event->openf1_session_key);

        if ($results->isEmpty()) {
            return;
        }

        $enriched = 0;

        foreach ($results as $result) {
            $driverNumber = (int) $result['driver_number'];
            $seasonDriver = $this->resolveSeasonDriver($driverNumber, $seasonDriverMap, $openF1Drivers);

            if (! $seasonDriver) {
                continue;
            }

            $durations = $result['duration'] ?? [];
            $isClassified = ! $result['dnf'] && ! $result['dns'] && ! $result['dsq'];

            if (! $isClassified || empty($durations)) {
                continue;
            }

            $update = array_filter([
                'q1_time' => isset($durations[0]) ? $this->secondsToTime((float) $durations[0]) : null,
                'q2_time' => isset($durations[1]) ? $this->secondsToTime((float) $durations[1]) : null,
                'q3_time' => isset($durations[2]) ? $this->secondsToTime((float) $durations[2]) : null,
            ]);

            if (! empty($update)) {
                EventResult::where('event_id', $event->id)
                    ->where('driver_id', $seasonDriver->driver_id)
                    ->update($update);
                $enriched++;
            }
        }

        if ($enriched > 0) {
            $this->line("  Enriched {$enriched} qualifying times from OpenF1.");
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

    // ──────────────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────────────

    protected function resolveSeasonDriver(int $driverNumber, Collection $seasonDriverMap, Collection $openF1Drivers): ?SeasonDriver
    {
        if ($seasonDriver = $seasonDriverMap[$driverNumber] ?? null) {
            return $seasonDriver;
        }

        $openF1Driver = $openF1Drivers[$driverNumber] ?? null;

        if (! $openF1Driver) {
            return null;
        }

        $fullName = $openF1Driver['full_name'] ?? null;

        if (! $fullName) {
            return null;
        }

        $match = $seasonDriverMap->first(fn ($sd) => mb_strtolower($sd->driver->name) === mb_strtolower($fullName));

        if ($match) {
            $this->line("  → Matched {$fullName} (#{$driverNumber}) by name to #{$seasonDriverMap->search($match)}");
        }

        return $match;
    }

    protected function syncDriverPhotos(Collection $driverMap, Collection $openF1Drivers): void
    {
        $driversNeedingPhotos = $driverMap->filter(fn ($sd) => ! $sd->driver->photo_path);

        if ($driversNeedingPhotos->isEmpty()) {
            return;
        }

        $synced = 0;

        foreach ($driversNeedingPhotos as $number => $seasonDriver) {
            $headshotUrl = $openF1Drivers[$number]['headshot_url'] ?? null;

            if (! $headshotUrl) {
                continue;
            }

            $seasonDriver->driver->update(['photo_path' => $headshotUrl]);
            $synced++;
        }

        if ($synced > 0) {
            $this->line("  Synced {$synced} driver photo(s).");
        }
    }

    protected function syncConstructorColours(Collection $driverMap, Collection $openF1Drivers): void
    {
        $constructorsNeedingColour = $driverMap
            ->unique('constructor_id')
            ->filter(fn ($sd) => ! $sd->constructor->team_colour);

        if ($constructorsNeedingColour->isEmpty()) {
            return;
        }

        $synced = 0;

        foreach ($constructorsNeedingColour as $number => $seasonDriver) {
            $teamColour = $openF1Drivers[$number]['team_colour'] ?? null;

            if (! $teamColour) {
                continue;
            }

            $seasonDriver->constructor->update(['team_colour' => $teamColour]);
            $synced++;
        }

        if ($synced > 0) {
            $this->line("  Synced {$synced} constructor colour(s).");
        }
    }

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

    /**
     * Convert a lap time string like "1:23.456" to time format "00:01:23.456".
     */
    protected function lapTimeToTime(string $lapTime): string
    {
        if (preg_match('/^(\d+):(\d+\.\d+)$/', $lapTime, $matches)) {
            return sprintf('00:%02d:%06.3f', (int) $matches[1], (float) $matches[2]);
        }

        return '00:00:00.000';
    }
}
