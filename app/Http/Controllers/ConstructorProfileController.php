<?php

namespace App\Http\Controllers;

use App\Models\Constructor;
use App\Models\Franchise;
use App\Models\Season;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConstructorProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $franchiseFilter = $request->query('franchise');

        $constructors = Constructor::query()
            ->where('is_active', true)
            ->with(['franchise', 'country'])
            ->when($franchiseFilter, fn ($query) => $query->whereHas('franchise', fn ($query) => $query->where('slug', $franchiseFilter)))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Constructors/Index', [
            'constructors' => $constructors,
            'franchises' => Franchise::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => [
                'franchise' => $franchiseFilter,
            ],
        ]);
    }

    public function show(Constructor $constructor): Response
    {
        $constructor->load(['franchise', 'country']);

        $activeSeason = $constructor->franchise->activeSeason();

        $currentDrivers = $activeSeason
            ? $constructor->seasonDrivers()
                ->where('season_id', $activeSeason->id)
                ->whereNull('effective_to')
                ->with('driver.country')
                ->get()
                ->map(fn ($sd) => [
                    'id' => $sd->driver->id,
                    'name' => $sd->driver->name,
                    'slug' => $sd->driver->slug,
                    'number' => $sd->number,
                    'country' => $sd->driver->country,
                ])
            : collect();

        $stats = $constructor->constructorSeasonStats()->get();

        $careerSummary = [
            'seasons' => $stats->count(),
            'races_entered' => $stats->sum('races_entered'),
            'wins' => $stats->sum('wins'),
            'podiums' => $stats->sum('podiums'),
            'one_twos' => $stats->sum('one_twos'),
            'poles' => $stats->sum('poles'),
            'fastest_laps' => $stats->sum('fastest_laps'),
            'points_total' => $stats->sum('points_total'),
            'best_championship' => $stats->min('championship_position'),
        ];

        $seasonStats = $constructor->constructorSeasonStats()
            ->with('season:id,name,year')
            ->orderByDesc(Season::query()->select('year')->whereColumn('seasons.id', 'constructor_season_stats.season_id'))
            ->get();

        $latestStat = $seasonStats->first();
        $fantasyStats = [
            'ownership_pct' => $latestStat?->fantasy_ownership_pct,
            'avg_points' => $stats->avg('fantasy_points_total'),
            'best_haul' => $stats->max('fantasy_points_total'),
        ];

        $availableSeasons = $constructor->constructorSeasonStats()
            ->join('seasons', 'seasons.id', '=', 'constructor_season_stats.season_id')
            ->orderByDesc('seasons.year')
            ->get(['seasons.id', 'seasons.name', 'seasons.year']);

        return Inertia::render('Constructors/Show', [
            'constructor' => $constructor,
            'currentDrivers' => $currentDrivers,
            'careerSummary' => $careerSummary,
            'seasonStats' => $seasonStats,
            'fantasyStats' => $fantasyStats,
            'availableSeasons' => $availableSeasons,
        ]);
    }

    public function season(Constructor $constructor, Season $season): Response
    {
        $constructor->load(['franchise', 'country']);

        $seasonStat = $constructor->constructorSeasonStats()
            ->where('season_id', $season->id)
            ->firstOrFail();

        $seasonDrivers = $constructor->seasonDrivers()
            ->where('season_id', $season->id)
            ->with('driver.country')
            ->get()
            ->map(fn ($sd) => [
                'id' => $sd->driver->id,
                'name' => $sd->driver->name,
                'slug' => $sd->driver->slug,
                'number' => $sd->number,
                'country' => $sd->driver->country,
            ]);

        $eventResults = $constructor->eventResults()
            ->whereHas('event', fn ($query) => $query->where('season_id', $season->id)->where('status', 'completed'))
            ->with(['event.track:id,name', 'driver:id,name,slug'])
            ->orderBy('event_id')
            ->get()
            ->groupBy('event_id')
            ->map(fn ($results) => [
                'event' => $results->first()->event->only('id', 'name'),
                'track' => $results->first()->event->track->only('id', 'name'),
                'results' => $results->map(fn ($r) => [
                    'driver' => $r->driver->only('id', 'name', 'slug'),
                    'grid_position' => $r->grid_position,
                    'finish_position' => $r->finish_position,
                    'status' => $r->status,
                    'fastest_lap' => $r->fastest_lap,
                    'driver_of_the_day' => $r->driver_of_the_day,
                ]),
            ])
            ->values();

        $availableSeasons = $constructor->constructorSeasonStats()
            ->join('seasons', 'seasons.id', '=', 'constructor_season_stats.season_id')
            ->orderByDesc('seasons.year')
            ->get(['seasons.id', 'seasons.name', 'seasons.year']);

        return Inertia::render('Constructors/Show', [
            'constructor' => $constructor,
            'season' => $season->only('id', 'name', 'year'),
            'seasonStat' => $seasonStat,
            'seasonDrivers' => $seasonDrivers,
            'eventResults' => $eventResults,
            'availableSeasons' => $availableSeasons,
        ]);
    }
}
