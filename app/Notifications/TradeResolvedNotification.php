<?php

namespace App\Notifications;

use App\Models\Trade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TradeResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Trade $trade) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->trade->status;
        $leagueName = $this->trade->league->name;
        $subject = $status === 'completed'
            ? "Trade accepted in {$leagueName}"
            : "Trade declined in {$leagueName}";

        $line = $status === 'completed'
            ? "Your trade offer in **{$leagueName}** has been **accepted** and the rosters have been updated."
            : "Your trade offer in **{$leagueName}** has been **declined**.";

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Trade Update')
            ->line($line)
            ->action('View League', url("/leagues/{$this->trade->league->slug}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trade_resolved',
            'trade_id' => $this->trade->id,
            'trade_status' => $this->trade->status,
            'league_id' => $this->trade->league_id,
            'league_name' => $this->trade->league->name,
        ];
    }
}
