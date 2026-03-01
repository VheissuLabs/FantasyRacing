<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FantasyEventPoint;
use App\Models\League;
use App\Notifications\EventPointsCalculatedNotification;
use App\Services\PointsCalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalculateEventPoints implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Event $event) {}

    public function handle(PointsCalculationService $calculator): void
    {
        $calculator->calculateForEvent($this->event);

        $this->notifyTeams();

        RefreshSeasonStats::dispatch($this->event->load('season')->season);
    }

    protected function notifyTeams(): void
    {
        $leagues = League::where('season_id', $this->event->season_id)->with('fantasyTeams.user')->get();

        foreach ($leagues as $league) {
            foreach ($league->fantasyTeams as $team) {
                $totalPoints = FantasyEventPoint::where('fantasy_team_id', $team->id)
                    ->where('event_id', $this->event->id)
                    ->sum('points');

                $team->user?->notify(new EventPointsCalculatedNotification($this->event, (float) $totalPoints));
            }
        }
    }
}
