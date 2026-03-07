<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Season;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $franchiseFilter = $request->cookie('franchise');
        $seasonId = $request->cookie('season_id');

        $drivers = Driver::query()
            ->where('is_active', true)
            ->with([
                'country',
                'franchise',
                'seasonDrivers' => fn ($query) => $query
                    ->when(
                        $seasonId,
                        fn ($q) => $q->where('season_id', $seasonId),
                        fn ($q) => $q->whereHas('season', fn ($sq) => $sq->where('is_active', true)),
                    )
                    ->with('constructor:id,name,slug'),
            ])
            ->when($franchiseFilter, fn ($query) => $query->whereHas('franchise', fn ($q) => $q->where('slug', $franchiseFilter)))
            ->when($seasonId, fn ($query) => $query->whereHas('seasonDrivers', fn ($q) => $q->where('season_id', $seasonId)))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Drivers/Index', [
            'drivers' => $drivers,
        ]);
    }

    public function show(Request $request, Driver $driver): Response
    {
        $driver->load(['country', 'franchise']);

        $seasonId = $request->cookie('season_id');
        $season = $seasonId ? Season::find($seasonId) : $driver->franchise->activeSeason();

        $currentSeasonDriver = $season
            ? $driver->seasonDrivers()
                ->where('season_id', $season->id)
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
            'best_championship' => $stats->min('championship_position'),
        ];

        $seasonStats = $driver->driverSeasonStats()
            ->with(['season:id,name,year', 'constructor:id,name,slug'])
            ->orderByDesc(Season::query()->select('year')->whereColumn('seasons.id', 'driver_season_stats.season_id'))
            ->get();

        $allResults = $driver->eventResults()
            ->whereHas('event', fn ($query) => $query->where('status', 'completed'))
            ->with(['event.track:id,name', 'event:id,name,type,season_id,track_id', 'constructor:id,name,slug'])
            ->join('events', 'events.id', '=', 'event_results.event_id')
            ->join('seasons', 'seasons.id', '=', 'events.season_id')
            ->orderByDesc('seasons.year')
            ->orderBy('events.sort_order')
            ->select('event_results.*')
            ->get();

        $resultsBySeason = $allResults->groupBy(fn ($r) => $r->event->season_id)
            ->map(function ($results) {
                $season = Season::find($results->first()->event->season_id);

                return [
                    'season' => $season->only('id', 'name', 'year'),
                    'results' => $results->values(),
                ];
            })
            ->values();

        $availableSeasons = $driver->driverSeasonStats()
            ->join('seasons', 'seasons.id', '=', 'driver_season_stats.season_id')
            ->orderByDesc('seasons.year')
            ->get(['seasons.id', 'seasons.name', 'seasons.year']);

        return Inertia::render('Drivers/Show', [
            'driver' => $driver,
            'currentSeasonDriver' => $currentSeasonDriver,
            'careerSummary' => $careerSummary,
            'seasonStats' => $seasonStats,
            'resultsBySeason' => $resultsBySeason,
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
