<?php

use App\Jobs\SendLeagueInviteEmail;
use App\Models\LeagueInvite;
use App\Models\User;
use App\Notifications\LeagueInviteNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

test('job sends league invite notification to the invite email', function () {
    Notification::fake();

    $invite = LeagueInvite::factory()->create();

    (new SendLeagueInviteEmail($invite))->handle();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        LeagueInviteNotification::class,
        function ($notification, $channels, $notifiable) use ($invite) {
            return $notifiable->routes['mail'] === $invite->email;
        }
    );
});

test('notification uses mail channel', function () {
    $invite = LeagueInvite::factory()->create();
    $notification = new LeagueInviteNotification($invite);

    expect($notification->via(new AnonymousNotifiable))->toBe(['mail']);
});

test('mail subject contains league name', function () {
    $invite = LeagueInvite::factory()->create();
    $notification = new LeagueInviteNotification($invite);

    $mail = $notification->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toContain($invite->league->name);
});

test('mail action url points to invite token path', function () {
    $invite = LeagueInvite::factory()->create();
    $notification = new LeagueInviteNotification($invite);

    $mail = $notification->toMail(new AnonymousNotifiable);

    expect($mail->actionUrl)->toContain("/invites/{$invite->token}");
});

test('mail body contains inviter name', function () {
    $inviter = User::factory()->create(['name' => 'Jane Doe']);
    $invite = LeagueInvite::factory()->create(['invited_by' => $inviter->id]);
    $notification = new LeagueInviteNotification($invite);

    $mail = $notification->toMail(new AnonymousNotifiable);
    $introLines = implode(' ', $mail->introLines);

    expect($introLines)->toContain('Jane Doe');
});

test('mail body contains expiration date', function () {
    $invite = LeagueInvite::factory()->create([
        'expires_at' => now()->addDays(7),
    ]);
    $notification = new LeagueInviteNotification($invite);

    $mail = $notification->toMail(new AnonymousNotifiable);
    $outroLines = implode(' ', $mail->outroLines);

    expect($outroLines)->toContain($invite->expires_at->toFormattedDateString());
});
