<?php

namespace App\Notifications;

use App\Models\DraftSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DraftStartingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly DraftSession $session) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $league = $this->session->league;
        $timezone = $notifiable->timezone ?? 'UTC';
        $scheduledAt = $this->session->scheduled_at->timezone($timezone)->toDayDateTimeString();

        return (new MailMessage)
            ->subject("Draft starting soon: {$league->name}")
            ->greeting('Draft Alert!')
            ->line("The draft for **{$league->name}** is starting soon.")
            ->line("Scheduled time: {$scheduledAt}")
            ->action('Go to League', url("/leagues/{$league->slug}"))
            ->line('Make sure you\'re ready when the clock starts!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'draft_starting',
            'league_id' => $this->session->league_id,
            'league_name' => $this->session->league->name,
            'draft_session_id' => $this->session->id,
            'scheduled_at' => $this->session->scheduled_at?->toIso8601String(),
        ];
    }
}
