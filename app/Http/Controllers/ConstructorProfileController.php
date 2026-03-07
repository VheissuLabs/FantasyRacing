<?php

namespace App\Http\Controllers;

use App\Models\Constructor;
use App\Models\EventConstructorResult;
use App\Models\Season;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConstructorProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $franchiseFilter = $request->cookie('franchise');
        $seasonId = $request->cookie('season_id');

        $constructors = Constructor::query()
            ->where('is_active', true)
            ->with(['franchise', 'country'])
            ->when($franchiseFilter, fn ($query) => $query->whereHas('franchise', fn ($q) => $q->where('slug', $franchiseFilter)))
            ->when($seasonId, fn ($query) => $query->whereHas('seasonConstructors', fn ($q) => $q->where('season_id', $seasonId)))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Constructors/Index', [
            'constructors' => $constructors,
        ]);
    }

    public function show(Request $request, Constructor $constructor): Response
    {
        $constructor->load(['franchise', 'country']);

        $seasonId = $request->cookie('season_id');
        $season = $seasonId ? Season::find($seasonId) : $constructor->franchise->activeSeason();

        $currentDrivers = $season
            ? $constructor->seasonDrivers()
                ->where('season_id', $season->id)
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
            'best_championship' => $stats->min('championship_position'),
        ];

        $seasonStats = $constructor->constructorSeasonStats()
            ->with('season:id,name,year')
            ->orderByDesc(Season::query()->select('year')->whereColumn('seasons.id', 'constructor_season_stats.season_id'))
            ->get();

        $constructorResults = EventConstructorResult::where('constructor_id', $constructor->id)
            ->get()
            ->keyBy('event_id');

        $allResults = $constructor->eventResults()
            ->whereHas('event', fn ($query) => $query->where('status', 'completed'))
            ->with(['event.track:id,name', 'event:id,name,type,season_id,track_id', 'driver:id,name,slug'])
            ->join('events', 'events.id', '=', 'event_results.event_id')
            ->join('seasons', 'seasons.id', '=', 'events.season_id')
            ->orderByDesc('seasons.year')
            ->orderBy('events.sort_order')
            ->select('event_results.*')
            ->get();

        $resultsBySeason = $allResults->groupBy(fn ($r) => $r->event->season_id)
            ->map(function ($seasonResults) use ($constructorResults) {
                $season = Season::find($seasonResults->first()->event->season_id);

                $eventGroups = $seasonResults->groupBy('event_id')
                    ->map(function ($results) use ($constructorResults) {
                        $eventId = $results->first()->event_id;
                        $constructorResult = $constructorResults[$eventId] ?? null;

                        return [
                            'event' => $results->first()->event->only('id', 'name', 'type'),
                            'track' => $results->first()->event->track?->only('id', 'name'),
                            'fantasy_points' => $constructorResult?->fantasy_points,
                            'results' => $results->map(fn ($r) => [
                                'driver' => $r->driver->only('id', 'name', 'slug'),
                                'grid_position' => $r->grid_position,
                                'finish_position' => $r->finish_position,
                                'status' => $r->status,
                                'fastest_lap' => $r->fastest_lap,
                                'driver_of_the_day' => $r->driver_of_the_day,
                                'fia_points' => $r->fia_points,
                                'fantasy_points' => $r->fantasy_points,
                            ]),
                        ];
                    })
                    ->values();

                return [
                    'season' => $season->only('id', 'name', 'year'),
                    'events' => $eventGroups,
                ];
            })
            ->values();

        $availableSeasons = $constructor->constructorSeasonStats()
            ->join('seasons', 'seasons.id', '=', 'constructor_season_stats.season_id')
            ->orderByDesc('seasons.year')
            ->get(['seasons.id', 'seasons.name', 'seasons.year']);

        return Inertia::render('Constructors/Show', [
            'constructor' => $constructor,
            'currentDrivers' => $currentDrivers,
            'careerSummary' => $careerSummary,
            'seasonStats' => $seasonStats,
            'resultsBySeason' => $resultsBySeason,
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

        $constructorResults = EventConstructorResult::where('constructor_id', $constructor->id)
            ->whereHas('event', fn ($query) => $query->where('season_id', $season->id))
            ->with('event:id,name,type')
            ->get()
            ->keyBy('event_id');

        $eventResults = $constructor->eventResults()
            ->whereHas('event', fn ($query) => $query->where('season_id', $season->id)->where('status', 'completed'))
            ->with(['event.track:id,name', 'event:id,name,type,track_id', 'driver:id,name,slug'])
            ->orderBy('event_id')
            ->get()
            ->groupBy('event_id')
            ->map(function ($results) use ($constructorResults) {
                $eventId = $results->first()->event_id;
                $constructorResult = $constructorResults[$eventId] ?? null;

                return [
                    'event' => $results->first()->event->only('id', 'name', 'type'),
                    'track' => $results->first()->event->track?->only('id', 'name'),
                    'fantasy_points' => $constructorResult?->fantasy_points,
                    'results' => $results->map(fn ($r) => [
                        'driver' => $r->driver->only('id', 'name', 'slug'),
                        'grid_position' => $r->grid_position,
                        'finish_position' => $r->finish_position,
                        'status' => $r->status,
                        'fastest_lap' => $r->fastest_lap,
                        'driver_of_the_day' => $r->driver_of_the_day,
                        'fia_points' => $r->fia_points,
                        'fantasy_points' => $r->fantasy_points,
                    ]),
                ];
            })
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
