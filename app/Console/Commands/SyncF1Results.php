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
use Illuminate\Support\Str;

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
            CalculateEventPoints::dispatchSync($event);
            $this->line("  Calculated points for {$event->name}.");
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
        $this->backfillMissingQualifyingResults($event);
    }

    /**
     * Create "not_classified" qualifying entries for drivers who raced
     * but have no qualifying result (e.g. crashed in qualifying).
     */
    protected function backfillMissingQualifyingResults(Event $raceEvent): void
    {
        $qualifyingEvent = Event::where('season_id', $raceEvent->season_id)
            ->where('round', $raceEvent->round)
            ->where('type', 'qualifying')
            ->first();

        if (! $qualifyingEvent) {
            return;
        }

        $raceDriverIds = EventResult::where('event_id', $raceEvent->id)->pluck('driver_id');
        $qualifiedDriverIds = EventResult::where('event_id', $qualifyingEvent->id)->pluck('driver_id');
        $missingDriverIds = $raceDriverIds->diff($qualifiedDriverIds);

        if ($missingDriverIds->isEmpty()) {
            return;
        }

        $maxPosition = EventResult::where('event_id', $qualifyingEvent->id)->max('finish_position') ?? 0;

        foreach ($missingDriverIds as $driverId) {
            $raceResult = EventResult::where('event_id', $raceEvent->id)
                ->where('driver_id', $driverId)
                ->first();

            $maxPosition++;

            EventResult::updateOrCreate(
                ['event_id' => $qualifyingEvent->id, 'driver_id' => $driverId],
                [
                    'constructor_id' => $raceResult->constructor_id,
                    'finish_position' => $maxPosition,
                    'grid_position' => null,
                    'status' => 'not_classified',
                    'data_source' => 'derived',
                ],
            );
        }

        CalculateEventPoints::dispatchSync($qualifyingEvent);
        $this->line("  Backfilled {$missingDriverIds->count()} missing qualifying result(s) and recalculated points.");
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

        // Enrich race/sprint with grid positions, fastest lap, and overtakes
        if (in_array($event->type, ['race', 'sprint'])) {
            $this->enrichRaceData($event, $openF1, $seasonDriverMap, $openF1Drivers);
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

    protected function enrichRaceData(Event $event, OpenF1 $openF1, Collection $seasonDriverMap, Collection $openF1Drivers): void
    {
        $sessionKey = $event->openf1_session_key;

        $this->enrichGridPositions($event, $openF1, $sessionKey, $seasonDriverMap, $openF1Drivers);
        $this->enrichFastestLap($event, $openF1, $sessionKey, $seasonDriverMap, $openF1Drivers);
        $this->enrichOvertakes($event, $openF1, $sessionKey, $seasonDriverMap, $openF1Drivers);
    }

    protected function enrichGridPositions(Event $event, OpenF1 $openF1, int $sessionKey, Collection $seasonDriverMap, Collection $openF1Drivers): void
    {
        $positions = $openF1->getPositions($sessionKey);

        if ($positions->isEmpty()) {
            return;
        }

        // The first position entry per driver represents their grid position
        $gridPositions = $positions->groupBy('driver_number')
            ->map(fn ($entries) => $entries->sortBy('date')->first()['position'] ?? null)
            ->filter();

        $enriched = 0;

        foreach ($gridPositions as $driverNumber => $gridPosition) {
            $seasonDriver = $this->resolveSeasonDriver((int) $driverNumber, $seasonDriverMap, $openF1Drivers);

            if (! $seasonDriver) {
                continue;
            }

            $updated = EventResult::where('event_id', $event->id)
                ->where('driver_id', $seasonDriver->driver_id)
                ->whereNull('grid_position')
                ->update(['grid_position' => (int) $gridPosition]);

            $enriched += $updated;
        }

        if ($enriched > 0) {
            $this->line("  Enriched {$enriched} grid positions from OpenF1.");
        }
    }

    protected function enrichFastestLap(Event $event, OpenF1 $openF1, int $sessionKey, Collection $seasonDriverMap, Collection $openF1Drivers): void
    {
        $laps = $openF1->getLaps($sessionKey);

        if ($laps->isEmpty()) {
            return;
        }

        // Filter out pit out laps and laps without a duration
        $validLaps = $laps->filter(fn ($lap) => ! ($lap['is_pit_out_lap'] ?? false) && isset($lap['lap_duration']) && $lap['lap_duration'] > 0);

        if ($validLaps->isEmpty()) {
            return;
        }

        $fastestLap = $validLaps->sortBy('lap_duration')->first();
        $fastestDriverNumber = (int) $fastestLap['driver_number'];
        $seasonDriver = $this->resolveSeasonDriver($fastestDriverNumber, $seasonDriverMap, $openF1Drivers);

        if (! $seasonDriver) {
            return;
        }

        // Clear any existing fastest lap flag, then set the new one
        EventResult::where('event_id', $event->id)->update(['fastest_lap' => false]);
        EventResult::where('event_id', $event->id)
            ->where('driver_id', $seasonDriver->driver_id)
            ->update(['fastest_lap' => true]);

        $this->line("  Fastest lap: {$seasonDriver->driver->name} ({$fastestLap['lap_duration']}s)");
    }

    protected function enrichOvertakes(Event $event, OpenF1 $openF1, int $sessionKey, Collection $seasonDriverMap, Collection $openF1Drivers): void
    {
        $positions = $openF1->getPositions($sessionKey);

        if ($positions->isEmpty()) {
            return;
        }

        // Count non-starters (DNS) and their grid positions for first-lap adjustment
        $nonStarterGridPositions = EventResult::where('event_id', $event->id)
            ->where('status', 'dns')
            ->pluck('grid_position')
            ->filter()
            ->values();

        $enriched = 0;

        $grouped = $positions->groupBy('driver_number');

        foreach ($grouped as $driverNumber => $entries) {
            $seasonDriver = $this->resolveSeasonDriver((int) $driverNumber, $seasonDriverMap, $openF1Drivers);

            if (! $seasonDriver) {
                continue;
            }

            $result = EventResult::where('event_id', $event->id)
                ->where('driver_id', $seasonDriver->driver_id)
                ->first();

            if (! $result) {
                continue;
            }

            $sorted = $entries->sortBy('date')->values();
            $gridPosition = $result->grid_position;
            $dnsAhead = $gridPosition ? $nonStarterGridPositions->filter(fn ($p) => $p < $gridPosition)->count() : 0;

            $overtakes = $this->countOvertakes($sorted, $positions, $gridPosition, $dnsAhead);

            EventResult::where('event_id', $event->id)
                ->where('driver_id', $seasonDriver->driver_id)
                ->update(['overtakes_made' => $overtakes]);
            $enriched++;
        }

        if ($enriched > 0) {
            $this->line("  Enriched {$enriched} driver overtake counts from OpenF1.");
        }
    }

    /**
     * Count overtakes from OpenF1 position data.
     *
     * Each position decrease is counted as 1 overtake event. Filters applied:
     * 1. Sustained: for small gains (≤2 positions), the exact position must be held for ≥10s.
     *    For large gains (≥3), the driver must still be better than pre-gain position at 10s.
     * 2. Passed car check: if the car being passed was failing or pitting (dropped 5+
     *    positions in 60s), the pass doesn't count per F1 rules.
     * 3. Same-level dedup: same position gained twice within 30s counts as 1
     * 4. Pre-gain volatility: for small gains, skip if >5 changes in prior 30s (first-lap chaos)
     * 5. Post-gain volatility: skip if >5 changes in next 15s (position oscillation)
     *
     * When the first-lap gain is too volatile to count individually, first-lap overtakes
     * are estimated as: (grid position - DNS cars ahead) - settled position.
     */
    protected function countOvertakes(Collection $sorted, Collection $allPositions, ?int $gridPosition = null, int $dnsAhead = 0): int
    {
        $overtakes = 0;
        $recentGains = [];

        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev = $sorted[$i - 1]['position'];
            $curr = $sorted[$i]['position'];

            if ($curr >= $prev) {
                continue;
            }

            $gainedAt = strtotime($sorted[$i]['date']);
            $positionsGained = $prev - $curr;

            // 1. Sustained check (varies by gain size)
            if ($positionsGained >= 3) {
                // Large gains: position at 10s must still be better than pre-gain
                $posAt10s = $curr;

                for ($j = $i + 1; $j < $sorted->count(); $j++) {
                    $nextAt = strtotime($sorted[$j]['date']);

                    if ($nextAt - $gainedAt >= 10) {
                        break;
                    }

                    $posAt10s = $sorted[$j]['position'];
                }

                if ($posAt10s >= $prev) {
                    continue;
                }
            } else {
                // Small gains: exact position (or better) must be held for ≥10s
                $sustained = true;

                for ($j = $i + 1; $j < $sorted->count(); $j++) {
                    $nextAt = strtotime($sorted[$j]['date']);

                    if ($nextAt - $gainedAt >= 10) {
                        break;
                    }

                    if ($sorted[$j]['position'] > $curr) {
                        $sustained = false;
                        break;
                    }
                }

                if (! $sustained) {
                    continue;
                }
            }

            // 2. Net loss + passed car check
            // If driver drops below pre-gain position within 120s, check why:
            // - If the car passed was failing (5+ pos drop in 60s), it wasn't a real overtake
            // - If the car passed was racing normally, the gain was real despite later loss
            $droppedBelowPrev = false;

            for ($j = $i + 1; $j < $sorted->count(); $j++) {
                $nextAt = strtotime($sorted[$j]['date']);

                if ($nextAt - $gainedAt > 120) {
                    break;
                }

                if ($sorted[$j]['position'] > $prev) {
                    $droppedBelowPrev = true;
                    break;
                }
            }

            if ($droppedBelowPrev) {
                // Check if the passed car was failing — if so, this wasn't a real overtake
                // If the passed car was racing normally, the gain was real (driver just lost it later)
                $passedCarFailing = $this->passedCarWasFailingOrPitting($allPositions, $sorted[$i]['date'], $curr, $sorted[$i]['driver_number']);

                if ($passedCarFailing) {
                    continue;
                }
            }

            // 3. Same-level dedup: same position gained within 30s counts as 1,
            //    unless the car being passed is different (separate overtake)
            $passedDriverNumber = $this->findPassedDriver($allPositions, $sorted[$i]['date'], $curr, $sorted[$i]['driver_number']);

            if (isset($recentGains[$curr]) && ($gainedAt - $recentGains[$curr]['time']) < 30) {
                if ($passedDriverNumber && $passedDriverNumber === $recentGains[$curr]['driver']) {
                    $recentGains[$curr] = ['time' => $gainedAt, 'driver' => $passedDriverNumber];

                    continue;
                }
            }

            $recentGains[$curr] = ['time' => $gainedAt, 'driver' => $passedDriverNumber];

            // 4. Pre-gain volatility: for small gains, skip if >5 changes in prior 30s
            if ($positionsGained <= 2) {
                $changes = 0;

                for ($j = $i - 1; $j >= 0; $j--) {
                    $prevAt = strtotime($sorted[$j]['date']);

                    if ($gainedAt - $prevAt > 30) {
                        break;
                    }

                    $changes++;
                }

                if ($changes > 5) {
                    continue;
                }
            }

            // 5. Post-gain volatility: skip if >5 changes in next 15s (oscillation)
            $postChanges = 0;

            for ($j = $i + 1; $j < $sorted->count(); $j++) {
                $nextAt = strtotime($sorted[$j]['date']);

                if ($nextAt - $gainedAt > 15) {
                    break;
                }

                $postChanges++;
            }

            if ($postChanges > 5) {
                // First-lap large gain killed by volatility: estimate from grid position
                if ($i === 1 && $gridPosition && $positionsGained >= 3) {
                    $overtakes += $this->estimateFirstLapOvertakes($sorted, $gridPosition, $dnsAhead);
                }

                continue;
            }

            $overtakes++;
        }

        return $overtakes;
    }

    /**
     * Estimate first-lap overtakes when position data is too volatile.
     *
     * Uses the driver's settled position (first position held ≥60s) compared
     * to their effective grid position (adjusted for DNS cars ahead).
     */
    protected function estimateFirstLapOvertakes(Collection $sorted, int $gridPosition, int $dnsAhead): int
    {
        $effectiveGrid = $gridPosition - $dnsAhead;

        // Find settled position: first position after the opening gain that's held for ≥60s
        for ($i = 1; $i < $sorted->count() - 1; $i++) {
            $heldUntil = strtotime($sorted[$i + 1]['date']) - strtotime($sorted[$i]['date']);

            if ($heldUntil >= 60) {
                return max(0, $effectiveGrid - $sorted[$i]['position']);
            }
        }

        // Fallback: use the last recorded position
        return max(0, $effectiveGrid - $sorted->last()['position']);
    }

    /**
     * Find the driver number at a specific position at a given timestamp (excluding our driver).
     */
    protected function findDriverAtPosition(Collection $allPositions, string $date, int $position, int $ourDriverNumber): ?int
    {
        $driver = $allPositions->first(function ($p) use ($date, $position, $ourDriverNumber) {
            return $p['date'] === $date
                && $p['position'] === $position
                && $p['driver_number'] !== $ourDriverNumber;
        });

        return $driver ? (int) $driver['driver_number'] : null;
    }

    /**
     * Find the driver number of the car that was passed (dropped from the gained position).
     */
    protected function findPassedDriver(Collection $allPositions, string $gainDate, int $gainedPosition, int $ourDriverNumber): ?int
    {
        $passedDriver = $allPositions->first(function ($p) use ($gainDate, $gainedPosition, $ourDriverNumber) {
            return $p['date'] === $gainDate
                && $p['position'] === $gainedPosition + 1
                && $p['driver_number'] !== $ourDriverNumber;
        });

        return $passedDriver ? (int) $passedDriver['driver_number'] : null;
    }

    /**
     * Check if the car being passed was suffering a failure or entering the pits.
     *
     * Finds the driver who held the gained position and checks if they dropped
     * 5+ positions within 60s — indicating a car failure or pit entry.
     */
    protected function passedCarWasFailingOrPitting(Collection $allPositions, string $gainDate, int $gainedPosition, int $ourDriverNumber): bool
    {
        // Find the driver who dropped from the gained position at this timestamp
        $passedDriver = $allPositions->first(function ($p) use ($gainDate, $gainedPosition, $ourDriverNumber) {
            return $p['date'] === $gainDate
                && $p['position'] === $gainedPosition + 1
                && $p['driver_number'] !== $ourDriverNumber;
        });

        if (! $passedDriver) {
            return false;
        }

        $gainTime = strtotime($gainDate);

        // Check if the passed driver dropped 5+ positions in the next 60s
        $passedEntries = $allPositions->where('driver_number', $passedDriver['driver_number'])
            ->filter(fn ($p) => strtotime($p['date']) >= $gainTime && strtotime($p['date']) <= $gainTime + 60)
            ->sortBy('date');

        if ($passedEntries->isEmpty()) {
            return false;
        }

        $startPos = $passedEntries->first()['position'];
        $worstPos = $passedEntries->max('position');

        return ($worstPos - $startPos) >= 5;
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
        // First try to match by number, but verify the slug matches to avoid
        // collisions (e.g. Verstappen #3 vs Ricciardo #3 in the same season)
        $candidate = $driverMap[$number] ?? null;

        if ($candidate && $candidate->slug === Str::slug(trim($result['Driver']['givenName'] . ' ' . $result['Driver']['familyName']))) {
            return $candidate;
        }

        // Check if this driver already exists in the map under a different number
        $driver = $this->f1->resolveDriver($result['Driver'], $event->season->franchise);
        $existingEntry = $driverMap->first(fn ($d) => $d->id === $driver->id);

        if ($existingEntry) {
            return $existingEntry;
        }

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
        if (in_array($jolpicaStatus, ['Finished', 'Lapped']) || str_starts_with($jolpicaStatus, '+')) {
            return 'classified';
        }

        if ($jolpicaStatus === 'Disqualified') {
            return 'dsq';
        }

        $normalized = strtolower($jolpicaStatus);

        if (in_array($normalized, ['did not start', 'withdrew', 'not classified'])) {
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
