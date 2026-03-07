<?php

namespace App\Http\Controllers\Leagues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leagues\PickupFreeAgentRequest;
use App\Http\Requests\Leagues\StoreFantasyTeamRequest;
use App\Http\Requests\Leagues\SwapRosterRequest;
use App\Http\Requests\Leagues\UpdateFantasyTeamRequest;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Services\RosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FantasyTeamController extends Controller
{
    public function create(League $league): Response
    {
        $user = Auth::user();

        abort_unless($league->members()->where('user_id', $user->id)->exists(), 403);
        abort_if($league->fantasyTeams()->where('user_id', $user->id)->exists(), 409);

        return Inertia::render('Leagues/Teams/Create', [
            'league' => $league->load(['franchise', 'season']),
        ]);
    }

    public function store(StoreFantasyTeamRequest $request, League $league): RedirectResponse
    {
        $team = FantasyTeam::create([
            'league_id' => $league->id,
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
        ]);

        return redirect()->route('leagues.teams.show', [$league->slug, $team->id])
            ->with('success', 'Team created!');
    }

    public function show(League $league, FantasyTeam $team): Response
    {
        abort_if($team->league_id !== $league->id, 404);

        $viewer = Auth::user();

        $team->load([
            'user',
            'roster' => fn ($query) => $query->with('entity'),
            'league.season',
        ]);

        $seasonId = $team->league->season_id;

        $team->roster->each(function ($entry) use ($seasonId) {
            if ($entry->entity_type !== 'driver') {
                return;
            }

            $driver = $entry->entity;
            $driver->load('country');
            $entry->entity->country_emoji = $driver->country?->emoji;
            $entry->entity->constructor_name = $driver->currentConstructor($seasonId)?->name;
        });

        $pointsByEvent = $team->fantasyPoints()
            ->with('event:id,name,type,scheduled_at,round')
            ->orderBy('event_id')
            ->get()
            ->groupBy('event_id')
            ->map(fn ($rows) => [
                'event' => $rows->first()->event,
                'total' => (float) $rows->sum('points'),
                'breakdown' => $rows->map(fn ($row) => [
                    'entity_type' => $row->entity_type,
                    'entity_id' => $row->entity_id,
                    'points' => (float) $row->points,
                ])->values(),
            ])
            ->values();

        $freeAgents = $league->freeAgentPool()
            ->with('entity')
            ->get();

        return Inertia::render('Leagues/Team/Show', [
            'league' => $league->load(['franchise', 'season', 'commissioner']),
            'team' => $team,
            'isOwner' => $viewer?->id === $team->user_id,
            'pointsByEvent' => $pointsByEvent,
            'totalPoints' => (float) $pointsByEvent->sum('total'),
            'freeAgents' => $freeAgents,
        ]);
    }

    public function update(UpdateFantasyTeamRequest $request, League $league, FantasyTeam $team): RedirectResponse
    {
        abort_if($team->league_id !== $league->id, 404);

        $team->update(['name' => $request->validated('name')]);

        return back()->with('success', 'Team name updated.');
    }

    public function swapRoster(SwapRosterRequest $request, League $league, FantasyTeam $team, RosterService $roster): RedirectResponse
    {
        abort_if($team->league_id !== $league->id, 404);
        Gate::authorize('manageRoster', $team);

        $roster->swapBenchDriver(
            $team,
            $request->integer('bench_driver_id'),
            $request->integer('in_seat_driver_id'),
        );

        return back()->with('success', 'Roster updated.');
    }

    public function pickupFreeAgent(PickupFreeAgentRequest $request, League $league, FantasyTeam $team, RosterService $roster): RedirectResponse
    {
        abort_if($team->league_id !== $league->id, 404);
        Gate::authorize('manageRoster', $team);

        $roster->pickupFreeAgent(
            $team,
            $league,
            $request->string('entity_type'),
            $request->integer('pickup_entity_id'),
            $request->integer('drop_entity_id'),
        );

        return back()->with('success', 'Roster updated.');
    }
}
