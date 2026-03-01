<?php

namespace App\Http\Controllers\Leagues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leagues\UpdateLeagueRequest;
use App\Models\Franchise;
use App\Models\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LeagueSettingsController extends Controller
{
    public function edit(League $league): Response
    {
        Gate::authorize('update', $league);

        return Inertia::render('Leagues/Settings/Edit', [
            'league' => $league->only('id', 'name', 'slug', 'description', 'max_teams', 'visibility', 'join_policy', 'invite_code', 'rules', 'franchise_id'),
            'franchises' => Franchise::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(UpdateLeagueRequest $request, League $league): RedirectResponse
    {
        $joinPolicy = $request->input('join_policy');
        $inviteCode = $league->invite_code;

        if ($joinPolicy === 'invite_only' && ! $inviteCode) {
            $inviteCode = Str::upper(Str::random(8));
        } elseif ($joinPolicy !== 'invite_only') {
            $inviteCode = null;
        }

        $league->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'max_teams' => $request->input('max_teams'),
            'visibility' => $request->input('visibility'),
            'join_policy' => $joinPolicy,
            'invite_code' => $inviteCode,
            'rules' => array_merge($league->rules ?? [], $request->input('rules', [])),
        ]);

        return back()->with('success', 'League settings updated.');
    }

    public function regenerateInviteCode(Request $request, League $league): RedirectResponse
    {
        Gate::authorize('update', $league);

        $league->update([
            'invite_code' => Str::upper(Str::random(8)),
        ]);

        return back()->with('success', 'Invite code regenerated.');
    }
}
