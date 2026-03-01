<?php

namespace App\Http\Controllers\Leagues;

use App\Events\Draft\DraftScheduled;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leagues\DraftPickRequest;
use App\Http\Requests\Leagues\DraftScheduleRequest;
use App\Http\Requests\Leagues\DraftSetupRequest;
use App\Jobs\SendDraftStartingNotifications;
use App\Models\DraftSession;
use App\Models\League;
use App\Models\SeasonConstructor;
use App\Models\SeasonDriver;
use App\Services\DraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class DraftController extends Controller
{
    public function show(League $league): Response
    {
        $league->load(['franchise', 'season', 'commissioner']);

        $session = $league->draftSession()->with(['orders.fantasyTeam.user', 'picks.fantasyTeam.user'])->first();

        $availableDrivers = [];
        $availableConstructors = [];

        if ($session) {
            $pickedDriverIds = $session->picks()->where('entity_type', 'driver')->pluck('entity_id');
            $pickedConstructorIds = $session->picks()->where('entity_type', 'constructor')->pluck('entity_id');

            $availableDrivers = SeasonDriver::where('season_id', $league->season_id)
                ->whereNotIn('driver_id', $pickedDriverIds)
                ->with('driver:id,name,slug', 'constructor:id,name')
                ->get();

            $availableConstructors = SeasonConstructor::where('season_id', $league->season_id)
                ->whereNotIn('constructor_id', $pickedConstructorIds)
                ->with('constructor:id,name,slug')
                ->get();
        }

        $myTeam = auth()->user()
            ? $league->fantasyTeams()->where('user_id', auth()->id())->first()
            : null;

        $isCommissioner = $league->isCommissioner(auth()->user());

        $teamCount = $league->fantasyTeams()->count();

        $allDrivers = SeasonDriver::where('season_id', $league->season_id)
            ->with('driver:id,name,slug', 'constructor:id,name')
            ->get();

        $allConstructors = SeasonConstructor::where('season_id', $league->season_id)
            ->with('constructor:id,name,slug')
            ->get();

        $teams = $league->fantasyTeams()->with('user:id,name')->inRandomOrder()->get(['id', 'name', 'league_id', 'user_id']);

        return Inertia::render('Leagues/Draft/Show', [
            'league' => $league,
            'session' => $session,
            'availableDrivers' => $availableDrivers,
            'availableConstructors' => $availableConstructors,
            'allDrivers' => $allDrivers,
            'allConstructors' => $allConstructors,
            'teams' => $teams,
            'myTeam' => $myTeam,
            'isCommissioner' => $isCommissioner,
            'teamCount' => $teamCount,
        ]);
    }

    public function setup(DraftSetupRequest $request, League $league, DraftService $draftService): RedirectResponse
    {
        abort_unless($league->isCommissioner(auth()->user()), 403);
        abort_if($league->draftSession()->exists(), 409, 'Draft session already exists.');

        $teamCount = $league->fantasyTeams()->count();
        abort_if($teamCount < 2, 422, 'At least 2 teams are needed to start a draft.');

        $validated = $request->validated();

        $session = DraftSession::create([
            'league_id' => $league->id,
            'type' => $validated['type'],
            'pick_time_limit_seconds' => max($validated['pick_time_limit_seconds'], 10),
            'status' => 'pending',
            'total_picks' => 0,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        $draftService->randomizeOrder($session);

        if ($session->scheduled_at) {
            SendDraftStartingNotifications::dispatch($session, $validated['present_user_ids'] ?? []);
        }

        return back()->with('success', 'Draft session created.');
    }

    public function start(League $league, DraftService $draftService): RedirectResponse
    {
        abort_unless($league->isCommissioner(auth()->user()), 403);

        $session = $league->draftSession;
        abort_if(! $session, 404);
        if ($session->status !== 'pending') {
            return back();
        }

        try {
            $draftService->start($session);
        } catch (RuntimeException $e) {
            return back()->withErrors(['draft' => $e->getMessage()]);
        }

        return back()->with('success', 'Draft started!');
    }

    public function pause(League $league, DraftService $draftService): RedirectResponse
    {
        abort_unless($league->isCommissioner(auth()->user()), 403);

        $session = $league->draftSession;
        abort_if(! $session, 404);

        try {
            $draftService->pause($session, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['draft' => $e->getMessage()]);
        }

        return back()->with('success', 'Draft paused.');
    }

    public function resume(League $league, DraftService $draftService): RedirectResponse
    {
        abort_unless($league->isCommissioner(auth()->user()), 403);

        $session = $league->draftSession;
        abort_if(! $session, 404);

        try {
            $draftService->resume($session);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['draft' => $e->getMessage()]);
        }

        return back()->with('success', 'Draft resumed.');
    }

    public function updateOrder(Request $request, League $league, DraftService $draftService): RedirectResponse
    {
        abort_unless($league->isCommissioner(auth()->user()), 403);

        $session = $league->draftSession;
        abort_if(! $session, 404, 'No draft session found.');
        abort_unless($session->status === 'pending', 422, 'Can only update order for pending drafts.');

        $validated = $request->validate([
            'team_ids' => ['required', 'array'],
            'team_ids.*' => ['required', 'integer'],
        ]);

        try {
            $draftService->reorderTeams($session, $validated['team_ids']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['draft' => $e->getMessage()]);
        }

        return back()->with('success', 'Draft order updated.');
    }

    public function schedule(DraftScheduleRequest $request, League $league): RedirectResponse
    {
        abort_unless($league->isCommissioner(auth()->user()), 403);

        $session = $league->draftSession;
        abort_if(! $session, 404, 'No draft session found.');
        abort_unless($session->status === 'pending', 422, 'Can only schedule pending drafts.');

        $session->update([
            'scheduled_at' => $request->validated('scheduled_at'),
            'notified_at' => null,
        ]);

        DraftScheduled::dispatch($session->fresh());
        SendDraftStartingNotifications::dispatch($session, $request->validated('present_user_ids', []));

        return back()->with('success', 'Draft date scheduled and members notified.');
    }

    public function restart(League $league, DraftService $draftService): RedirectResponse
    {
        abort_unless($league->isCommissioner(auth()->user()), 403);

        $session = $league->draftSession;
        abort_if(! $session, 404);
        abort_unless(in_array($session->status, ['active', 'paused', 'completed']), 422, 'Draft cannot be restarted from its current state.');

        $draftService->restart($session);

        return back()->with('success', 'Draft has been restarted.');
    }

    public function pick(DraftPickRequest $request, League $league, DraftService $draftService): RedirectResponse
    {
        $session = $league->draftSession;
        abort_if(! $session, 404);

        $myTeam = $league->fantasyTeams()->where('user_id', $request->user()->id)->firstOrFail();

        try {
            $draftService->makePick(
                $session,
                $myTeam,
                $request->string('entity_type'),
                $request->integer('entity_id'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['draft' => $e->getMessage()]);
        }

        return back();
    }
}
