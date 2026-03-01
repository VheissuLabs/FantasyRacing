<?php

namespace App\Http\Controllers\Leagues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leagues\StoreInviteRequest;
use App\Jobs\SendLeagueInviteEmail;
use App\Models\League;
use App\Models\LeagueInvite;
use App\Models\LeagueMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InviteController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invite = LeagueInvite::where('token', $token)->with('league.franchise')->firstOrFail();

        if (! $invite->isPending()) {
            return Inertia::render('Invites/Show', [
                'invite' => $invite,
                'expired' => $invite->isExpired(),
                'alreadyUsed' => $invite->status === 'accepted',
            ]);
        }

        return Inertia::render('Invites/Show', [
            'invite' => $invite,
            'expired' => false,
            'alreadyUsed' => false,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = LeagueInvite::where('token', $token)->firstOrFail();

        abort_if(! $invite->isPending(), 422, 'This invite is no longer valid.');

        $user = $request->user();
        $league = $invite->league;

        abort_if($league->isFull(), 422, 'This league is full.');
        abort_if($league->members()->where('user_id', $user->id)->exists(), 422, 'You are already a member.');

        $invite->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_by' => $user->id,
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        return to_route('leagues.show', $league->slug);
    }

    public function store(StoreInviteRequest $request, League $league): RedirectResponse
    {
        abort_if(! $league->isCommissioner($request->user()), 403);

        $invite = $league->invites()->create([
            'invited_by' => $request->user()->id,
            'email' => $request->validated('email'),
            'token' => Str::random(32),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        SendLeagueInviteEmail::dispatch($invite);

        return back()->with('success', "Invite sent to {$invite->email}.");
    }

    public function destroy(Request $request, League $league, LeagueInvite $invite): RedirectResponse
    {
        abort_if(! $league->isCommissioner($request->user()), 403);

        $invite->update(['status' => 'expired']);

        return back();
    }

    public function resend(Request $request, League $league, LeagueInvite $invite): RedirectResponse
    {
        abort_if(! $league->isCommissioner($request->user()), 403);
        abort_if($invite->status !== 'pending', 422, 'Invite is not pending.');

        $invite->update(['expires_at' => now()->addDays(7)]);

        SendLeagueInviteEmail::dispatch($invite);

        return back()->with('success', 'Invite resent.');
    }
}
