<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\League;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StandingsApiController extends Controller
{
    public function show(League $league): JsonResponse
    {
        abort_if($league->visibility === 'private' && ! Auth::check(), 403);

        $standings = $league->fantasyTeams()
            ->with('user:id,name')
            ->withSum('fantasyPoints as total_points', 'points')
            ->orderByDesc('total_points')
            ->get()
            ->map(fn ($team, $index) => [
                'rank' => $index + 1,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'user' => $team->user,
                'total_points' => (float) ($team->total_points ?? 0),
            ]);

        return response()->json([
            'league_id' => $league->id,
            'league_name' => $league->name,
            'standings' => $standings,
        ]);
    }
}
