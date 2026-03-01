<?php

namespace App\Jobs;

use App\Models\DraftSession;
use App\Notifications\DraftStartingNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDraftStartingNotifications implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, int>  $excludeUserIds
     */
    public function __construct(
        public readonly DraftSession $session,
        public readonly array $excludeUserIds = [],
    ) {}

    public function handle(): void
    {
        $this->session->loadMissing('league.members.user');

        $users = $this->session->league->members->map->user->filter();

        if ($this->excludeUserIds) {
            $users = $users->whereNotIn('id', $this->excludeUserIds);
        }

        foreach ($users as $user) {
            $user->notify(new DraftStartingNotification($this->session));
        }

        $this->session->update(['notified_at' => now()]);
    }
}
