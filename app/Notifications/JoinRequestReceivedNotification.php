<?php

namespace App\Notifications;

use App\Models\League;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JoinRequestReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly League $league,
        public readonly User $requester,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->requester->name} wants to join {$this->league->name}")
            ->greeting('New Join Request')
            ->line("**{$this->requester->name}** has requested to join your league **{$this->league->name}**.")
            ->action('Review Request', url("/leagues/{$this->league->slug}"))
            ->line('Log in to approve or reject the request.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'join_request_received',
            'league_id' => $this->league->id,
            'league_name' => $this->league->name,
            'requester_id' => $this->requester->id,
            'requester_name' => $this->requester->name,
        ];
    }
}
