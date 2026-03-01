<?php

namespace App\Http\Controllers\Leagues;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\League;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LeagueDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        $franchiseFilter = $request->query('franchise');
        $seasonFilter = $request->query('season');
        $availableOnly = $request->boolean('available');
        $joinPolicyFilter = $request->query('join_policy');

        $leagues = League::query()
            ->where('visibility', 'public')
            ->where('is_active', true)
            ->with(['franchise', 'season', 'commissioner'])
            ->withCount('members')
            ->when($franchiseFilter, fn ($query) => $query->whereHas('franchise', fn ($query) => $query->where('slug', $franchiseFilter)))
            ->when($seasonFilter, fn ($query) => $query->whereHas('season', fn ($query) => $query->where('year', $seasonFilter)))
            ->when($availableOnly, fn ($query) => $query->where(fn ($query) => $query->whereNull('max_teams')->orWhereRaw('max_teams > (SELECT COUNT(*) FROM league_members WHERE league_members.league_id = leagues.id)')))
            ->when($joinPolicyFilter, fn ($query) => $query->where('join_policy', $joinPolicyFilter))
            ->orderByDesc('created_at')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Leagues/Index', [
            'leagues' => $leagues,
            'franchises' => Franchise::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'seasons' => Season::when($franchiseFilter, fn ($query) => $query->whereHas('franchise', fn ($query) => $query->where('slug', $franchiseFilter)))
                ->orderByDesc('year')->get(['id', 'name', 'year', 'franchise_id']),
            'filters' => [
                'franchise' => $franchiseFilter,
                'season' => $seasonFilter,
                'available' => $availableOnly,
                'join_policy' => $joinPolicyFilter,
            ],
        ]);
    }

    public function show(League $league): Response
    {
        abort_if($league->visibility === 'private' && ! Auth::check(), 403);

        $league->load(['franchise', 'season', 'commissioner']);
        $league->loadCount('members');

        $viewer = Auth::user();
        $membership = $viewer
            ? $league->members()->where('user_id', $viewer->id)->first()
            : null;

        $pendingRequest = $viewer
            ? $league->joinRequests()->where('user_id', $viewer->id)->where('status', 'pending')->first()
            : null;

        $fantasyTeam = $viewer
            ? $league->fantasyTeams()->where('user_id', $viewer->id)->first(['id', 'name'])
            : null;

        $isCommissioner = $viewer && $league->isCommissioner($viewer);

        $members = $league->members()->with([
            'user:id,name',
            'user.fantasyTeams' => fn ($query) => $query->where('league_id', $league->id)->select('id', 'user_id', 'name'),
        ])
            ->join('users', 'league_members.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->get(['league_members.id', 'league_members.league_id', 'league_members.user_id', 'league_members.role', 'league_members.joined_at'])
            ->each(function ($member): void {
                $member->user->setAttribute('fantasy_team', $member->user->fantasyTeams->first());
                $member->user->unsetRelation('fantasyTeams');
            });

        $invites = $isCommissioner
            ? $league->invites()->where('status', 'pending')->orderByDesc('created_at')->get(['id', 'email', 'status', 'expires_at', 'created_at'])
            : [];

        $joinRequests = $isCommissioner
            ? $league->joinRequests()->where('status', 'pending')->with('user:id,name')->orderByDesc('created_at')->get(['id', 'league_id', 'user_id', 'message', 'created_at'])
            : [];

        $inviteCodeUrl = $isCommissioner && $league->invite_code
            ? url("/join/{$league->invite_code}")
            : null;

        $standings = $league->fantasyTeams()
            ->select('id', 'name', 'user_id')
            ->with('user:id,name')
            ->withSum('fantasyPoints', 'points')
            ->orderByDesc('fantasy_points_sum_points')
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'user_name' => $team->user->name,
                'total_points' => (float) ($team->fantasy_points_sum_points ?? 0),
            ]);

        return Inertia::render('Leagues/Show', [
            'league' => $league,
            'members' => $members,
            'membership' => $membership,
            'pendingRequest' => $pendingRequest,
            'fantasyTeam' => $fantasyTeam,
            'isCommissioner' => $isCommissioner,
            'invites' => $invites,
            'joinRequests' => $joinRequests,
            'inviteCodeUrl' => $inviteCodeUrl,
            'standings' => $standings,
        ]);
    }
}
