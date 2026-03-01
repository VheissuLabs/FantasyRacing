<?php

namespace App\Events\Draft;

use App\Models\DraftSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftPaused implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DraftSession $session,
        public readonly int $pausedByUserId,
        public readonly string $pausedAt,
    ) {}

    public function broadcastAs(): string
    {
        return 'DraftPaused';
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
            'paused_by_user_id' => $this->pausedByUserId,
            'paused_at' => $this->pausedAt,
        ];
    }
}
