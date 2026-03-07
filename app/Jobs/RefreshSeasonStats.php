<?php

namespace App\Jobs;

use App\Models\ConstructorSeasonStat;
use App\Models\DriverSeasonStat;
use App\Models\Event;
use App\Models\EventConstructorResult;
use App\Models\EventResult;
use App\Models\FantasyTeamRoster;
use App\Models\PointsScheme;
use App\Models\Season;
use App\Models\SeasonConstructor;
use App\Models\SeasonDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class RefreshSeasonStats implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Season $season) {}

    public function handle(): void
    {
        $season = $this->season->loadMissing('franchise');
        $franchiseId = $season->franchise_id;

        $completedEventIds = Event::where('season_id', $season->id)
            ->where('status', 'completed')
            ->pluck('id');

        $totalTeams = $season->fantasyTeams()->count();

        $this->refreshDriverStats($season, $franchiseId, $completedEventIds, $totalTeams);
        $this->refreshConstructorStats($season, $franchiseId, $completedEventIds, $totalTeams);
    }

    private function refreshDriverStats(Season $season, int $franchiseId, Collection $eventIds, int $totalTeams): void
    {
        if ($eventIds->isEmpty()) {
            return;
        }

        $seasonDrivers = SeasonDriver::where('season_id', $season->id)
            ->whereNull('effective_to')
            ->get();

        $allResults = EventResult::whereIn('event_id', $eventIds)
            ->with('event:id,type')
            ->get()
            ->groupBy('driver_id');

        $fantasyTotals = EventResult::whereIn('event_id', $eventIds)
            ->whereNotNull('fantasy_points')
            ->selectRaw('driver_id, SUM(fantasy_points) as total')
            ->groupBy('driver_id')
            ->pluck('total', 'driver_id');

        $ownershipCounts = FantasyTeamRoster::where('entity_type', 'driver')
            ->whereHas('fantasyTeam.league', fn ($query) => $query->where('season_id', $season->id))
            ->selectRaw('entity_id, COUNT(DISTINCT fantasy_team_id) as team_count')
            ->groupBy('entity_id')
            ->pluck('team_count', 'entity_id');

        $driverStats = [];

        foreach ($seasonDrivers as $seasonDriver) {
            $driverId = $seasonDriver->driver_id;
            $results = $allResults->get($driverId, collect());

            $raceResults = $results->filter(fn ($result) => $result->event->type === 'race');
            $sprintResults = $results->filter(fn ($result) => $result->event->type === 'sprint');
            $qualifyingResults = $results->filter(fn ($result) => $result->event->type === 'qualifying');

            $classifiedRace = $raceResults->where('status', 'classified');
            $classifiedSprint = $sprintResults->where('status', 'classified');

            $pointsTotal = $classifiedRace->sum(fn ($result) => (float) PointsScheme::getPointsForPosition('race', $result->finish_position, $franchiseId));
            $bestFinish = $classifiedRace->min('finish_position');

            $teamsWithDriver = $ownershipCounts->get($driverId, 0);
            $ownershipPct = $totalTeams > 0 ? round($teamsWithDriver / $totalTeams * 100, 2) : null;

            $driverStats[$driverId] = [
                'driver_id' => $driverId,
                'season_id' => $season->id,
                'constructor_id' => $seasonDriver->constructor_id,
                'races_entered' => $raceResults->count(),
                'races_classified' => $classifiedRace->count(),
                'wins' => $classifiedRace->where('finish_position', 1)->count(),
                'podiums' => $classifiedRace->where('finish_position', '<=', 3)->count(),
                'poles' => $qualifyingResults->where('status', 'classified')->where('finish_position', 1)->count(),
                'fastest_laps' => $raceResults->where('fastest_lap', true)->count(),
                'dnfs' => $results->where('status', 'dnf')->count(),
                'sprint_wins' => $classifiedSprint->where('finish_position', 1)->count(),
                'sprint_podiums' => $classifiedSprint->where('finish_position', '<=', 3)->count(),
                'sprint_fastest_laps' => $sprintResults->where('fastest_lap', true)->count(),
                'points_total' => $pointsTotal,
                'best_finish' => $bestFinish,
                'championship_position' => null,
                'fantasy_points_total' => (float) $fantasyTotals->get($driverId, 0),
                'fantasy_ownership_pct' => $ownershipPct,
                'last_computed_at' => now(),
            ];
        }

        // Assign championship positions by points_total descending
        collect($driverStats)
            ->sortByDesc('points_total')
            ->values()
            ->each(function ($stat, $index) use (&$driverStats) {
                $driverStats[$stat['driver_id']]['championship_position'] = $index + 1;
            });

        foreach ($driverStats as $stat) {
            DriverSeasonStat::updateOrCreate(
                ['driver_id' => $stat['driver_id'], 'season_id' => $stat['season_id']],
                $stat,
            );
        }
    }

    private function refreshConstructorStats(Season $season, int $franchiseId, Collection $eventIds, int $totalTeams): void
    {
        if ($eventIds->isEmpty()) {
            return;
        }

        $seasonConstructors = SeasonConstructor::where('season_id', $season->id)->get();

        $allResults = EventResult::whereIn('event_id', $eventIds)
            ->with('event:id,type')
            ->get()
            ->groupBy('constructor_id');

        $fantasyTotals = EventConstructorResult::whereIn('event_id', $eventIds)
            ->whereNotNull('fantasy_points')
            ->selectRaw('constructor_id as entity_id, SUM(fantasy_points) as total')
            ->groupBy('constructor_id')
            ->pluck('total', 'entity_id');

        $ownershipCounts = FantasyTeamRoster::where('entity_type', 'constructor')
            ->whereHas('fantasyTeam.league', fn ($query) => $query->where('season_id', $season->id))
            ->selectRaw('entity_id, COUNT(DISTINCT fantasy_team_id) as team_count')
            ->groupBy('entity_id')
            ->pluck('team_count', 'entity_id');

        $constructorStats = [];

        foreach ($seasonConstructors as $seasonConstructor) {
            $constructorId = $seasonConstructor->constructor_id;
            $results = $allResults->get($constructorId, collect());

            $raceResults = $results->filter(fn ($result) => $result->event->type === 'race');
            $sprintResults = $results->filter(fn ($result) => $result->event->type === 'sprint');
            $qualifyingResults = $results->filter(fn ($result) => $result->event->type === 'qualifying');

            $classifiedRace = $raceResults->where('status', 'classified');
            $classifiedSprint = $sprintResults->where('status', 'classified');

            // Count events where this constructor had entries (distinct event_ids in race results)
            $racesEntered = $raceResults->unique('event_id')->count();

            $pointsTotal = $classifiedRace->sum(fn ($result) => (float) PointsScheme::getPointsForPosition('race', $result->finish_position, $franchiseId));
            $bestFinish = $classifiedRace->min('finish_position');

            // Count events where constructor had 2 drivers finish 1st and 2nd (1-2 finish)
            $oneTwos = $raceResults
                ->groupBy('event_id')
                ->filter(function ($eventResults) {
                    $positions = $eventResults->where('status', 'classified')->pluck('finish_position')->sort()->values();

                    return $positions->first() === 1 && $positions->get(1) === 2;
                })
                ->count();

            $sprintOneTwos = $sprintResults
                ->groupBy('event_id')
                ->filter(function ($eventResults) {
                    $positions = $eventResults->where('status', 'classified')->pluck('finish_position')->sort()->values();

                    return $positions->first() === 1 && $positions->get(1) === 2;
                })
                ->count();

            $poles = $qualifyingResults->where('status', 'classified')->where('finish_position', 1)->count();

            $teamsWithConstructor = $ownershipCounts->get($constructorId, 0);
            $ownershipPct = $totalTeams > 0 ? round($teamsWithConstructor / $totalTeams * 100, 2) : null;

            $constructorStats[$constructorId] = [
                'constructor_id' => $constructorId,
                'season_id' => $season->id,
                'races_entered' => $racesEntered,
                'wins' => $classifiedRace->where('finish_position', 1)->count(),
                'podiums' => $classifiedRace->where('finish_position', '<=', 3)->count(),
                'one_twos' => $oneTwos,
                'poles' => $poles,
                'fastest_laps' => $raceResults->where('fastest_lap', true)->count(),
                'sprint_wins' => $classifiedSprint->where('finish_position', 1)->count(),
                'sprint_podiums' => $classifiedSprint->where('finish_position', '<=', 3)->count(),
                'sprint_one_twos' => $sprintOneTwos,
                'points_total' => $pointsTotal,
                'best_finish' => $bestFinish,
                'championship_position' => null,
                'fantasy_points_total' => (float) $fantasyTotals->get($constructorId, 0),
                'fantasy_ownership_pct' => $ownershipPct,
                'last_computed_at' => now(),
            ];
        }

        collect($constructorStats)
            ->sortByDesc('points_total')
            ->values()
            ->each(function ($stat, $index) use (&$constructorStats) {
                $constructorStats[$stat['constructor_id']]['championship_position'] = $index + 1;
            });

        foreach ($constructorStats as $stat) {
            ConstructorSeasonStat::updateOrCreate(
                ['constructor_id' => $stat['constructor_id'], 'season_id' => $stat['season_id']],
                $stat,
            );
        }
    }
}
