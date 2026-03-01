<?php

namespace App\Http\Controllers\Leagues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leagues\StoreTradeRequest;
use App\Models\League;
use App\Models\Trade;
use App\Services\TradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TradeController extends Controller
{
    public function index(League $league): Response
    {
        $league->load(['franchise', 'season', 'commissioner']);

        $viewer = auth()->user();
        $isCommissioner = $viewer && $league->isCommissioner($viewer);

        $myTeam = $viewer
            ? $league->fantasyTeams()->where('user_id', $viewer->id)->first()
            : null;

        $trades = $league->trades()
            ->with([
                'initiatorTeam.user:id,name',
                'receiverTeam.user:id,name',
                'items',
            ])
            ->latest('initiated_at')
            ->paginate(20);

        return Inertia::render('Leagues/Trades/Index', [
            'league' => $league,
            'trades' => $trades,
            'myTeam' => $myTeam,
            'isCommissioner' => $isCommissioner,
            'tradeApprovalRequired' => $league->tradeApprovalRequired(),
        ]);
    }

    public function create(League $league): Response
    {
        $league->load(['franchise', 'season']);

        $myTeam = $league->fantasyTeams()
            ->where('user_id', auth()->id())
            ->with(['roster' => fn ($query) => $query->with('entity')])
            ->firstOrFail();

        $otherTeams = $league->fantasyTeams()
            ->with(['user:id,name', 'roster' => fn ($query) => $query->with('entity')])
            ->where('id', '!=', $myTeam->id)
            ->get();

        $freeAgents = $league->freeAgentPool()->with('entity')->get();

        return Inertia::render('Leagues/Trades/Create', [
            'league' => $league,
            'myTeam' => $myTeam,
            'otherTeams' => $otherTeams,
            'freeAgents' => $freeAgents,
        ]);
    }

    public function store(StoreTradeRequest $request, League $league, TradeService $tradeService): RedirectResponse
    {
        $myTeam = $league->fantasyTeams()->where('user_id', $request->user()->id)->firstOrFail();
        $receiverTeam = $request->input('receiver_team_id')
            ? $league->fantasyTeams()->findOrFail($request->integer('receiver_team_id'))
            : null;

        $tradeService->propose(
            $league,
            $myTeam,
            $receiverTeam,
            $request->input('giving'),
            $request->input('receiving'),
        );

        return to_route('leagues.trades.index', $league->slug)
            ->with('success', 'Trade proposal submitted.');
    }

    public function accept(League $league, Trade $trade, TradeService $tradeService): RedirectResponse
    {
        abort_if($trade->league_id !== $league->id, 404);

        Gate::authorize('accept', $trade);

        $tradeService->accept($trade);

        return back()->with('success', 'Trade accepted.');
    }

    public function reject(League $league, Trade $trade, TradeService $tradeService): RedirectResponse
    {
        abort_if($trade->league_id !== $league->id, 404);

        Gate::authorize('reject', $trade);

        $tradeService->reject($trade);

        return back()->with('success', 'Trade declined.');
    }
}
