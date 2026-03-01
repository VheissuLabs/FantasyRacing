<?php

namespace App\Notifications;

use App\Models\Trade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TradeReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Trade $trade) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $initiatorName = $this->trade->initiatorTeam->user->name ?? 'A league member';
        $leagueName = $this->trade->league->name;

        return (new MailMessage)
            ->subject("Trade offer received in {$leagueName}")
            ->greeting('New Trade Offer')
            ->line("**{$initiatorName}** has sent you a trade offer in **{$leagueName}**.")
            ->action('Review Trade', url("/leagues/{$this->trade->league->slug}"))
            ->line('Log in to accept or reject the offer.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trade_received',
            'trade_id' => $this->trade->id,
            'league_id' => $this->trade->league_id,
            'league_name' => $this->trade->league->name,
            'initiator_team_name' => $this->trade->initiatorTeam->name,
        ];
    }
}
