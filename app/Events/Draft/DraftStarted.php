<?php

namespace App\Events\Draft;

use App\Models\DraftSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DraftSession $session,
        /** @var array<int, array{pick_number: int, team_id: int, round: int}> */
        public readonly array $pickOrder,
    ) {}

    public function broadcastAs(): string
    {
        return 'DraftStarted';
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
            'total_picks' => $this->session->total_picks,
            'pick_order' => $this->pickOrder,
        ];
    }
}
