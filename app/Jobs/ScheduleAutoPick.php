<?php

namespace App\Jobs;

use App\Models\DraftSession;
use App\Services\DraftService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScheduleAutoPick implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $draftSessionId,
        public readonly int $expectedPickNumber,
    ) {}

    public function handle(DraftService $draftService): void
    {
        $session = DraftSession::find($this->draftSessionId);

        if (! $session) {
            return;
        }

        // If the pick number has already advanced, a manual pick was made — do nothing.
        if ($session->current_pick_number !== $this->expectedPickNumber) {
            return;
        }

        // Only auto-pick if the draft is still active (not paused or completed).
        if (! $session->isActive()) {
            return;
        }

        $draftService->autoPick($session);
    }
}
