<?php

namespace App\Events\Draft;

use App\Models\DraftSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftResumed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DraftSession $session,
        public readonly int $currentPickNumber,
        public readonly int $teamId,
        public readonly ?string $timerExpiresAt,
    ) {}

    public function broadcastAs(): string
    {
        return 'DraftResumed';
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
            'current_pick_number' => $this->currentPickNumber,
            'team_id' => $this->teamId,
            'timer_expires_at' => $this->timerExpiresAt,
        ];
    }
}
