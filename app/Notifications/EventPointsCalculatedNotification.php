<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventPointsCalculatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Event $event,
        public readonly float $totalPoints,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Points calculated: {$this->event->name}")
            ->greeting('Fantasy Points Updated!')
            ->line("Points have been calculated for **{$this->event->name}**.")
            ->line("Your team scored **{$this->totalPoints}** points.")
            ->action('View Standings', url('/leagues'))
            ->line('Check your full breakdown in the app.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'event_points_calculated',
            'event_id' => $this->event->id,
            'event_name' => $this->event->name,
            'total_points' => $this->totalPoints,
        ];
    }
}
