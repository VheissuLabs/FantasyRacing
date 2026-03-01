<?php

namespace App\Events\Draft;

use App\Models\DraftSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftRestarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DraftSession $session,
    ) {}

    public function broadcastAs(): string
    {
        return 'DraftRestarted';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("draft.{$this->session->id}"),
        ];
    }
}
