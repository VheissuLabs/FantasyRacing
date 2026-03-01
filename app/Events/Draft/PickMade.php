<?php

namespace App\Events\Draft;

use App\Models\DraftPick;
use App\Models\DraftSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PickMade implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DraftSession $session,
        public readonly DraftPick $pick,
        public readonly string $entityName,
        public readonly ?int $nextPickNumber,
        public readonly ?int $nextTeamId,
        public readonly ?string $timerExpiresAt,
    ) {}

    public function broadcastAs(): string
    {
        return 'PickMade';
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
            'pick_number' => $this->pick->pick_number,
            'team_id' => $this->pick->fantasy_team_id,
            'entity_type' => $this->pick->entity_type,
            'entity_id' => $this->pick->entity_id,
            'entity_name' => $this->entityName,
            'is_auto_pick' => $this->pick->is_auto_pick,
            'next_pick_number' => $this->nextPickNumber,
            'next_team_id' => $this->nextTeamId,
            'timer_expires_at' => $this->timerExpiresAt,
        ];
    }
}
