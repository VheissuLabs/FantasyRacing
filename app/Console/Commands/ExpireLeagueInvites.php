<?php

namespace App\Console\Commands;

use App\Models\LeagueInvite;
use Illuminate\Console\Command;

class ExpireLeagueInvites extends Command
{
    protected $signature = 'invites:expire';

    protected $description = 'Mark all pending invites that have passed their expiry date as expired';

    public function handle(): int
    {
        $count = LeagueInvite::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} invite(s).");

        return Command::SUCCESS;
    }
}
