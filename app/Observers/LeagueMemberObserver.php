<?php

namespace App\Observers;

use App\Models\FantasyTeam;
use App\Models\LeagueMember;

class LeagueMemberObserver
{
    public function created(LeagueMember $leagueMember): void
    {
        FantasyTeam::create([
            'league_id' => $leagueMember->league_id,
            'user_id' => $leagueMember->user_id,
            'name' => $leagueMember->user->name . '\'s Team',
        ]);
    }
}
