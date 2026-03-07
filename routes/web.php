<?php

use App\Http\Controllers\ConstructorProfileController;
use App\Http\Controllers\DriverProfileController;
use App\Http\Controllers\Leagues\DraftController;
use App\Http\Controllers\Leagues\FantasyTeamController;
use App\Http\Controllers\Leagues\InviteController;
use App\Http\Controllers\Leagues\LeagueController;
use App\Http\Controllers\Leagues\LeagueDirectoryController;
use App\Http\Controllers\Leagues\LeagueJoinController;
use App\Http\Controllers\Leagues\LeagueSettingsController;
use App\Http\Controllers\Leagues\StandingsController;
use App\Http\Controllers\Leagues\TradeController;
use App\Models\Event;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    $nextEvent = Event::where('scheduled_at', '>', now())
        ->orderBy('scheduled_at')
        ->with('track')
        ->first(['id', 'season_id', 'track_id', 'name', 'type', 'scheduled_at', 'round']);

    if ($nextEvent) {
        $timezone = auth()->user()->timezone ?? 'UTC';
        $nextEvent->scheduled_at_human = $nextEvent->scheduled_at->diffForHumans();
        $nextEvent->scheduled_at_local = $nextEvent->scheduled_at->timezone($timezone)->toDayDateTimeString();
    }

    $user = auth()->user();
    $leagues = $user->leagues()
        ->where('is_active', true)
        ->with('franchise:id,name')
        ->withCount('members')
        ->get(['leagues.id', 'leagues.name', 'leagues.slug', 'leagues.franchise_id'])
        ->each(function ($league) use ($user): void {
            $league->setAttribute('fantasy_team_name', $league->fantasyTeams()->where('user_id', $user->id)->value('name'));
        });

    return Inertia::render('Dashboard', [
        'nextEvent' => $nextEvent,
        'leagues' => $leagues,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// Driver & Constructor profiles (public)
Route::get('/drivers', [DriverProfileController::class, 'index'])->name('drivers.index');
Route::get('/drivers/{driver:slug}', [DriverProfileController::class, 'show'])->name('drivers.show');
Route::get('/drivers/{driver:slug}/seasons/{season}', [DriverProfileController::class, 'season'])->name('drivers.season');
Route::get('/constructors', [ConstructorProfileController::class, 'index'])->name('constructors.index');
Route::get('/constructors/{constructor:slug}', [ConstructorProfileController::class, 'show'])->name('constructors.show');
Route::get('/constructors/{constructor:slug}/seasons/{season}', [ConstructorProfileController::class, 'season'])->name('constructors.season');

// League directory (public)
Route::get('/leagues', [LeagueDirectoryController::class, 'index'])->name('leagues.index');
Route::get('/leagues/create', [LeagueController::class, 'create'])->middleware('auth')->name('leagues.create');
Route::get('/leagues/{league:slug}', [LeagueDirectoryController::class, 'show'])->name('leagues.show');

// League authenticated routes
Route::middleware('auth')->group(function () {
    // League creation
    Route::post('/leagues', [LeagueController::class, 'store'])->name('leagues.store');

    // Join flows
    Route::post('/leagues/{league:slug}/join', [LeagueJoinController::class, 'join'])->name('leagues.join');
    Route::post('/leagues/{league:slug}/join-requests', [LeagueJoinController::class, 'request'])->name('leagues.join-requests.store');
    Route::delete('/leagues/{league:slug}/join-requests/{joinRequest}', [LeagueJoinController::class, 'cancel'])->name('leagues.join-requests.destroy');
    Route::post('/leagues/{league:slug}/join-requests/{joinRequest}/approve', [LeagueJoinController::class, 'approve'])->name('leagues.join-requests.approve');
    Route::post('/leagues/{league:slug}/join-requests/{joinRequest}/reject', [LeagueJoinController::class, 'reject'])->name('leagues.join-requests.reject');

    // Invites
    Route::post('/invites/{token}/accept', [InviteController::class, 'accept'])->name('invites.accept');
    Route::post('/leagues/{league:slug}/invites', [InviteController::class, 'store'])->name('leagues.invites.store');
    Route::delete('/leagues/{league:slug}/invites/{invite}', [InviteController::class, 'destroy'])->name('leagues.invites.destroy');
    Route::post('/leagues/{league:slug}/invites/{invite}/resend', [InviteController::class, 'resend'])->name('leagues.invites.resend');

    // Settings
    Route::get('/leagues/{league:slug}/settings', [LeagueSettingsController::class, 'edit'])->name('leagues.settings');
    Route::put('/leagues/{league:slug}/settings', [LeagueSettingsController::class, 'update'])->name('leagues.settings.update');
    Route::post('/leagues/{league:slug}/settings/regenerate-invite-code', [LeagueSettingsController::class, 'regenerateInviteCode'])->name('leagues.settings.regenerate-invite-code');
    Route::delete('/leagues/{league:slug}', [LeagueSettingsController::class, 'destroy'])->name('leagues.destroy');

    // Standings
    Route::get('/leagues/{league:slug}/standings', [StandingsController::class, 'show'])->name('leagues.standings');

    // Fantasy teams
    Route::get('/leagues/{league:slug}/teams/create', [FantasyTeamController::class, 'create'])->name('leagues.teams.create');
    Route::post('/leagues/{league:slug}/teams', [FantasyTeamController::class, 'store'])->name('leagues.teams.store');
    Route::get('/leagues/{league:slug}/teams/{team}', [FantasyTeamController::class, 'show'])->name('leagues.teams.show');
    Route::put('/leagues/{league:slug}/teams/{team}', [FantasyTeamController::class, 'update'])->name('leagues.teams.update');
    Route::post('/leagues/{league:slug}/teams/{team}/swap', [FantasyTeamController::class, 'swapRoster'])->name('leagues.teams.swap');
    Route::post('/leagues/{league:slug}/teams/{team}/pickup', [FantasyTeamController::class, 'pickupFreeAgent'])->name('leagues.teams.pickup');

    // Draft
    Route::get('/leagues/{league:slug}/draft', [DraftController::class, 'show'])->name('leagues.draft');
    Route::post('/leagues/{league:slug}/draft/setup', [DraftController::class, 'setup'])->name('leagues.draft.setup');
    Route::post('/leagues/{league:slug}/draft/start', [DraftController::class, 'start'])->name('leagues.draft.start');
    Route::post('/leagues/{league:slug}/draft/pause', [DraftController::class, 'pause'])->name('leagues.draft.pause');
    Route::post('/leagues/{league:slug}/draft/resume', [DraftController::class, 'resume'])->name('leagues.draft.resume');
    Route::put('/leagues/{league:slug}/draft/order', [DraftController::class, 'updateOrder'])->name('leagues.draft.update-order');
    Route::post('/leagues/{league:slug}/draft/schedule', [DraftController::class, 'schedule'])->name('leagues.draft.schedule');
    Route::post('/leagues/{league:slug}/draft/restart', [DraftController::class, 'restart'])->name('leagues.draft.restart');
    Route::post('/leagues/{league:slug}/draft/pick', [DraftController::class, 'pick'])->name('leagues.draft.pick');

    // Trades
    Route::get('/leagues/{league:slug}/trades', [TradeController::class, 'index'])->name('leagues.trades.index');
    Route::get('/leagues/{league:slug}/trades/create', [TradeController::class, 'create'])->name('leagues.trades.create');
    Route::post('/leagues/{league:slug}/trades', [TradeController::class, 'store'])->name('leagues.trades.store');
    Route::post('/leagues/{league:slug}/trades/{trade}/accept', [TradeController::class, 'accept'])->name('leagues.trades.accept');
    Route::post('/leagues/{league:slug}/trades/{trade}/reject', [TradeController::class, 'reject'])->name('leagues.trades.reject');
});

// Invite landing (public)
Route::get('/invites/{token}', [InviteController::class, 'show'])->name('invites.show');

// Invite code join (public landing, authenticated join)
Route::get('/join/{inviteCode}', [LeagueJoinController::class, 'showInviteCode'])->name('leagues.join-via-code');
Route::post('/join/{inviteCode}', [LeagueJoinController::class, 'joinViaCode'])->middleware('auth')->name('leagues.join-via-code.store');

require __DIR__.'/settings.php';

Route::get('/docs', fn () => Inertia::render('ComingSoon', ['title' => 'Documentation']))->name('docs');
