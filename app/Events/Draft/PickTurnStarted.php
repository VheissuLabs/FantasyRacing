<?php

namespace App\Events\Draft;

use App\Models\DraftSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PickTurnStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DraftSession $session,
        public readonly int $pickNumber,
        public readonly int $teamId,
        public readonly ?string $timerExpiresAt,
    ) {}

    public function broadcastAs(): string
    {
        return 'PickTurnStarted';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("draft.{$this->session->id}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'pick_number' => $this->pickNumber,
            'team_id' => $this->teamId,
            'timer_expires_at' => $this->timerExpiresAt,
        ];
    }
}
