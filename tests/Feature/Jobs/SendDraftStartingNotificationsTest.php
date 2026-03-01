<?php

use App\Jobs\SendDraftStartingNotifications;
use App\Models\DraftSession;
use App\Models\Franchise;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Season;
use App\Models\User;
use App\Notifications\DraftStartingNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->franchise = Franchise::factory()->create();
    $this->season = Season::factory()->create(['franchise_id' => $this->franchise->id]);
    $this->commissioner = User::factory()->create();
    $this->league = League::factory()->create([
        'franchise_id' => $this->franchise->id,
        'season_id' => $this->season->id,
        'commissioner_id' => $this->commissioner->id,
    ]);
});

test('job sends draft starting notification to all league members', function () {
    Notification::fake();

    $members = User::factory()->count(3)->create();
    foreach ($members as $member) {
        LeagueMember::factory()->create([
            'league_id' => $this->league->id,
            'user_id' => $member->id,
        ]);
    }

    $session = DraftSession::create([
        'league_id' => $this->league->id,
        'type' => 'snake',
        'status' => 'pending',
        'scheduled_at' => now()->addMinutes(15),
        'total_picks' => 10,
    ]);

    (new SendDraftStartingNotifications($session))->handle();

    Notification::assertCount(3);
    Notification::assertSentTo($members->first(), DraftStartingNotification::class);
});

test('job sets notified_at on the session', function () {
    Notification::fake();

    $session = DraftSession::create([
        'league_id' => $this->league->id,
        'type' => 'snake',
        'status' => 'pending',
        'scheduled_at' => now()->addMinutes(15),
        'total_picks' => 10,
    ]);

    expect($session->notified_at)->toBeNull();

    (new SendDraftStartingNotifications($session))->handle();

    $session->refresh();
    expect($session->notified_at)->not->toBeNull();
});

test('command dispatches job for eligible sessions', function () {
    Notification::fake();

    LeagueMember::factory()->create([
        'league_id' => $this->league->id,
        'user_id' => User::factory()->create()->id,
    ]);

    DraftSession::create([
        'league_id' => $this->league->id,
        'type' => 'snake',
        'status' => 'pending',
        'scheduled_at' => now()->addMinutes(15),
        'total_picks' => 10,
    ]);

    $this->artisan('draft:notify-starting')
        ->assertSuccessful();
});

test('command skips already notified sessions', function () {
    Notification::fake();

    LeagueMember::factory()->create([
        'league_id' => $this->league->id,
        'user_id' => User::factory()->create()->id,
    ]);

    DraftSession::create([
        'league_id' => $this->league->id,
        'type' => 'snake',
        'status' => 'pending',
        'scheduled_at' => now()->addMinutes(15),
        'notified_at' => now()->subMinutes(5),
        'total_picks' => 10,
    ]);

    $this->artisan('draft:notify-starting')
        ->expectsOutputToContain('No drafts starting')
        ->assertSuccessful();
});

test('command skips sessions outside time window', function () {
    Notification::fake();

    DraftSession::create([
        'league_id' => $this->league->id,
        'type' => 'snake',
        'status' => 'pending',
        'scheduled_at' => now()->addHours(2),
        'total_picks' => 10,
    ]);

    $this->artisan('draft:notify-starting')
        ->expectsOutputToContain('No drafts starting')
        ->assertSuccessful();
});
