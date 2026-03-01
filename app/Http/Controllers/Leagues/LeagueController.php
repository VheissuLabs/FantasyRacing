<?php

namespace App\Http\Controllers\Leagues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leagues\StoreLeagueRequest;
use App\Models\Franchise;
use App\Models\League;
use App\Models\LeagueMember;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LeagueController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Leagues/Settings/Create', [
            'franchises' => Franchise::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(StoreLeagueRequest $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $franchise = Franchise::findOrFail($data['franchise_id']);
        $season = $franchise->activeSeason();

        abort_if(! $season, 422, 'This franchise has no active season.');

        $rules = $data['rules'] ?? [];
        $maxTeams = $data['max_teams'] ?? null;

        if (($rules['no_duplicates'] ?? false) && ($maxTeams === null || $maxTeams > 7)) {
            $maxTeams = 7;
        }

        $league = League::create([
            ...\Illuminate\Support\Arr::except($data, ['rules']),
            'season_id' => $season->id,
            'max_teams' => $maxTeams,
            'slug' => Str::slug($data['name']).'-'.Str::random(6),
            'commissioner_id' => $user->id,
            'invite_code' => $data['join_policy'] === 'invite_only' ? Str::upper(Str::random(8)) : null,
            'is_active' => true,
            'rules' => $rules,
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role' => 'commissioner',
            'joined_at' => now(),
        ]);

        return to_route('leagues.show', $league->slug);
    }
}
