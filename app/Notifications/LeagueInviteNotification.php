<?php

namespace App\Notifications;

use App\Models\LeagueInvite;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeagueInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly LeagueInvite $invite) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invite = $this->invite;
        $league = $invite->league;
        $inviterName = $invite->inviter?->name ?? 'Someone';
        $acceptUrl = url("/invites/{$invite->token}");

        return (new MailMessage)
            ->subject("You're invited to join {$league->name}")
            ->greeting('Hi there!')
            ->line("{$inviterName} has invited you to join **{$league->name}** on Fantasy Racing.")
            ->action('Accept Invite', $acceptUrl)
            ->line("This invite expires on {$invite->expires_at->timezone($this->recipientTimezone($invite->email))->toFormattedDateString()}.")
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }

    private function recipientTimezone(string $email): string
    {
        return User::where('email', $email)->value('timezone') ?? 'UTC';
    }
}
