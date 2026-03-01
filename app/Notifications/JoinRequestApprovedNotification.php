<?php

namespace App\Notifications;

use App\Models\League;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JoinRequestApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly League $league) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've been approved to join {$this->league->name}")
            ->greeting('Welcome aboard!')
            ->line("Your request to join **{$this->league->name}** has been approved.")
            ->action('Go to League', url("/leagues/{$this->league->slug}"))
            ->line('Good luck this season!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'join_request_approved',
            'league_id' => $this->league->id,
            'league_name' => $this->league->name,
        ];
    }
}
