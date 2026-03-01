<?php

namespace App\Http\Controllers\Leagues;

use App\Http\Controllers\Controller;
use App\Models\League;
use Inertia\Inertia;
use Inertia\Response;

class StandingsController extends Controller
{
    public function show(League $league): Response
    {
        $league->load(['franchise', 'season', 'commissioner']);

        $teams = $league->fantasyTeams()
            ->with('user:id,name')
            ->withSum('fantasyPoints as total_points', 'points')
            ->orderByDesc('total_points')
            ->get()
            ->map(fn ($team, $index) => [
                'id' => $team->id,
                'name' => $team->name,
                'user' => $team->user,
                'total_points' => (float) ($team->total_points ?? 0),
                'rank' => $index + 1,
            ]);

        // Per-event scores for chart data
        $events = $league->season->events()
            ->where('status', 'completed')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'type', 'round']);

        return Inertia::render('Leagues/Standings/Index', [
            'league' => $league,
            'standings' => $teams,
            'events' => $events,
        ]);
    }
}
