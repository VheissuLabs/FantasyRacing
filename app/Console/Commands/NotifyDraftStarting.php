<?php

namespace App\Console\Commands;

use App\Jobs\SendDraftStartingNotifications;
use App\Models\DraftSession;
use Illuminate\Console\Command;

class NotifyDraftStarting extends Command
{
    protected $signature = 'draft:notify-starting
        {--minutes=30 : Notify for drafts starting within this many minutes}';

    protected $description = 'Send notifications to teams in draft sessions starting soon';

    public function handle(): int
    {
        $withinMinutes = (int) $this->option('minutes');

        $sessions = DraftSession::query()
            ->where('status', 'pending')
            ->whereNull('notified_at')
            ->whereBetween('scheduled_at', [now(), now()->addMinutes($withinMinutes)])
            ->get();

        if ($sessions->isEmpty()) {
            $this->info("No drafts starting within {$withinMinutes} minutes.");

            return Command::SUCCESS;
        }

        foreach ($sessions as $session) {
            SendDraftStartingNotifications::dispatch($session);
            $this->info("Dispatched notifications for draft: League #{$session->league_id}");
        }

        return Command::SUCCESS;
    }
}
