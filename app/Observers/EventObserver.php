<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\FantasyTeam;
use App\Models\RosterSnapshot;

class EventObserver
{
    public function updating(Event $event): void
    {
        if ($event->isDirty('status') && $event->status === 'locked') {
            $this->snapshotRosters($event);
        }
    }

    private function snapshotRosters(Event $event): void
    {
        $teams = FantasyTeam::whereHas('league', fn ($query) => $query->where('season_id', $event->season_id))
            ->with('roster')
            ->get();

        foreach ($teams as $team) {
            $snapshot = $team->roster->map(fn ($entry) => [
                'entity_type' => $entry->entity_type,
                'entity_id' => $entry->entity_id,
                'in_seat' => $entry->in_seat,
            ])->values()->all();

            RosterSnapshot::updateOrCreate(
                ['event_id' => $event->id, 'fantasy_team_id' => $team->id],
                ['snapshot' => $snapshot],
            );
        }
    }
}
