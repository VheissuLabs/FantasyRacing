<?php

namespace App\Http\Controllers\Leagues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leagues\StoreJoinRequestRequest;
use App\Models\League;
use App\Models\LeagueJoinRequest;
use App\Models\LeagueMember;
use App\Notifications\JoinRequestApprovedNotification;
use App\Notifications\JoinRequestReceivedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeagueJoinController extends Controller
{
    public function join(Request $request, League $league): RedirectResponse
    {
        abort_if($league->join_policy !== 'open', 403, 'This league does not allow open joining.');
        abort_if($league->isFull(), 422, 'This league is full.');

        $user = $request->user();
        abort_if($league->members()->where('user_id', $user->id)->exists(), 422, 'You are already a member of this league.');

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        return to_route('leagues.show', $league->slug);
    }

    public function request(StoreJoinRequestRequest $request, League $league): RedirectResponse
    {
        abort_if($league->join_policy !== 'request', 403, 'This league does not accept join requests.');

        $user = $request->user();
        abort_if($league->members()->where('user_id', $user->id)->exists(), 422, 'You are already a member.');
        abort_if($league->joinRequests()->where('user_id', $user->id)->where('status', 'pending')->exists(), 422, 'You already have a pending request.');

        $league->joinRequests()->create([
            'user_id' => $user->id,
            'message' => $request->input('message'),
            'status' => 'pending',
        ]);

        $league->commissioner->notify(new JoinRequestReceivedNotification($league, $user));

        return to_route('leagues.show', $league->slug);
    }

    public function cancel(Request $request, League $league, LeagueJoinRequest $joinRequest): RedirectResponse
    {
        abort_if($joinRequest->user_id !== $request->user()->id, 403);

        $joinRequest->delete();

        return to_route('leagues.show', $league->slug);
    }

    public function approve(Request $request, League $league, LeagueJoinRequest $joinRequest): RedirectResponse
    {
        abort_if(! $league->isCommissioner($request->user()), 403);
        abort_if(! $joinRequest->isPending(), 422, 'Request is no longer pending.');
        abort_if($league->isFull(), 422, 'League is full.');

        $joinRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $joinRequest->user_id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $joinRequest->user->notify(new JoinRequestApprovedNotification($league));

        return back();
    }

    public function reject(Request $request, League $league, LeagueJoinRequest $joinRequest): RedirectResponse
    {
        abort_if(! $league->isCommissioner($request->user()), 403);

        $joinRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back();
    }

    public function showInviteCode(string $inviteCode): Response|RedirectResponse
    {
        $league = League::where('invite_code', $inviteCode)
            ->with(['franchise:id,name', 'season:id,name', 'commissioner:id,name'])
            ->first();

        abort_if(! $league, 404);

        return Inertia::render('Leagues/Invite/Show', [
            'league' => $league->only('id', 'name', 'slug', 'invite_code') + [
                'franchise' => ['name' => $league->franchise->name],
                'season' => ['name' => $league->season->name],
                'commissioner' => ['name' => $league->commissioner->name],
                'members_count' => $league->members()->count(),
                'is_full' => $league->isFull(),
            ],
        ]);
    }

    public function joinViaCode(Request $request, string $inviteCode): RedirectResponse
    {
        $league = League::where('invite_code', $inviteCode)->firstOrFail();

        abort_if($league->isFull(), 422, 'This league is full.');

        $user = $request->user();
        abort_if(
            $league->members()->where('user_id', $user->id)->exists(),
            422,
            'You are already a member of this league.'
        );

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        return to_route('leagues.show', $league->slug);
    }
}
