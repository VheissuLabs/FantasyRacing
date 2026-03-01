<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\League;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeagueApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $leagues = League::query()
            ->where('visibility', 'public')
            ->where('is_active', true)
            ->with(['franchise:id,name,slug', 'season:id,name,year', 'commissioner:id,name'])
            ->withCount('members')
            ->when($request->query('franchise'), fn ($query, $value) => $query->whereHas('franchise', fn ($query) => $query->where('slug', $value)))
            ->when($request->query('season'), fn ($query, $value) => $query->whereHas('season', fn ($query) => $query->where('year', $value)))
            ->orderByDesc('created_at')
            ->paginate(24);

        return response()->json($leagues);
    }

    public function show(League $league): JsonResponse
    {
        abort_if($league->visibility === 'private' && ! Auth::check(), 403);

        $league->load(['franchise:id,name,slug', 'season:id,name,year', 'commissioner:id,name']);
        $league->loadCount('members');

        return response()->json($league);
    }

    public function teams(League $league): JsonResponse
    {
        abort_if($league->visibility === 'private' && ! Auth::check(), 403);

        $teams = $league->fantasyTeams()
            ->with(['user:id,name', 'roster' => fn ($query) => $query->with('entity')])
            ->withSum('fantasyPoints as total_points', 'points')
            ->orderByDesc('total_points')
            ->get();

        return response()->json($teams);
    }
}
