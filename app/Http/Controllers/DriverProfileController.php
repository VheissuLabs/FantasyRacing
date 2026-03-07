<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Franchise;
use App\Models\Season;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $franchiseFilter = $request->query('franchise');

        $drivers = Driver::query()
            ->where('is_active', true)
            ->with([
                'country',
                'franchise',
                'seasonDrivers' => fn ($query) => $query
                    ->whereNull('effective_to')
                    ->whereHas('season', fn ($query) => $query->where('is_active', true))
                    ->with('constructor:id,name,slug'),
            ])
            ->when($franchiseFilter, fn ($query) => $query->whereHas('franchise', fn ($query) => $query->where('slug', $franchiseFilter)))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Drivers/Index', [
            'drivers' => $drivers,
            'franchises' => Franchise::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => [
                'franchise' => $franchiseFilter,
            ],
        ]);
    }

    public function show(Driver $driver): Response
    {
        $driver->load(['country', 'franchise']);

        $activeSeason = $driver->franchise->activeSeason();

        $currentSeasonDriver = $activeSeason
            ? $driver->seasonDrivers()
                ->where('season_id', $activeSeason->id)
                ->whereNull('effective_to')
                ->with('constructor')
                ->first()
            : null;

        $stats = $driver->driverSeasonStats()->get();

        $careerSummary = [
            'seasons' => $stats->count(),
            'races_entered' => $stats->sum('races_entered'),
            'wins' => $stats->sum('wins'),
            'podiums' => $stats->sum('podiums'),
            'poles' => $stats->sum('poles'),
            'fastest_laps' => $stats->sum('fastest_laps'),
            'points_total' => $stats->sum('points_total'),
            'best_championship' => $stats->min('championship_position'),
            'fantasy_points_total' => $stats->sum('fantasy_points_total'),
        ];

        $seasonStats = $driver->driverSeasonStats()
            ->with(['season:id,name,year', 'constructor:id,name,slug'])
            ->orderByDesc(Season::query()->select('year')->whereColumn('seasons.id', 'driver_season_stats.season_id'))
            ->get();

        $latestStat = $seasonStats->first();
        $fantasyStats = [
            'ownership_pct' => $latestStat?->fantasy_ownership_pct,
            'avg_points' => $stats->avg('fantasy_points_total'),
            'best_haul' => $stats->max('fantasy_points_total'),
        ];

        $recentResults = $driver->eventResults()
            ->whereHas('event', fn ($query) => $query->where('status', 'completed'))
            ->with(['event.track:id,name', 'event:id,name,type,season_id', 'constructor:id,name,slug'])
            ->latest('id')
            ->limit(10)
            ->get();

        $availableSeasons = $driver->driverSeasonStats()
            ->join('seasons', 'seasons.id', '=', 'driver_season_stats.season_id')
            ->orderByDesc('seasons.year')
            ->get(['seasons.id', 'seasons.name', 'seasons.year']);

        return Inertia::render('Drivers/Show', [
            'driver' => $driver,
            'currentSeasonDriver' => $currentSeasonDriver,
            'careerSummary' => $careerSummary,
            'seasonStats' => $seasonStats,
            'fantasyStats' => $fantasyStats,
            'recentResults' => $recentResults,
            'availableSeasons' => $availableSeasons,
        ]);
    }

    public function season(Driver $driver, Season $season): Response
    {
        $driver->load(['country', 'franchise']);

        $seasonStat = $driver->driverSeasonStats()
            ->where('season_id', $season->id)
            ->with('constructor:id,name,slug')
            ->firstOrFail();

        $seasonDriver = $driver->seasonDrivers()
            ->where('season_id', $season->id)
            ->with('constructor:id,name,slug')
            ->first();

        $eventResults = $driver->eventResults()
            ->whereHas('event', fn ($query) => $query->where('season_id', $season->id)->where('status', 'completed'))
            ->with(['event.track:id,name', 'constructor:id,name,slug'])
            ->orderBy('event_id')
            ->get();

        $availableSeasons = $driver->driverSeasonStats()
            ->join('seasons', 'seasons.id', '=', 'driver_season_stats.season_id')
            ->orderByDesc('seasons.year')
            ->get(['seasons.id', 'seasons.name', 'seasons.year']);

        return Inertia::render('Drivers/Show', [
            'driver' => $driver,
            'season' => $season->only('id', 'name', 'year'),
            'seasonStat' => $seasonStat,
            'seasonDriver' => $seasonDriver,
            'eventResults' => $eventResults,
            'availableSeasons' => $availableSeasons,
        ]);
    }
}
