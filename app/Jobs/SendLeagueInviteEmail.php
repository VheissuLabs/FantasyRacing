<?php

namespace App\Jobs;

use App\Models\LeagueInvite;
use App\Notifications\LeagueInviteNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;

class SendLeagueInviteEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly LeagueInvite $invite) {}

    public function handle(): void
    {
        (new AnonymousNotifiable)
            ->route('mail', $this->invite->email)
            ->notify(new LeagueInviteNotification($this->invite));
    }
}
