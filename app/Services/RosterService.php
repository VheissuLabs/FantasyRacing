<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamRoster;
use App\Models\FreeAgentPool;
use App\Models\League;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RosterService
{
    /**
     * Swap the bench driver into a seat, moving the given in-seat driver to the bench.
     * Both must belong to the team's roster. The next event must not be locked.
     */
    public function swapBenchDriver(FantasyTeam $team, int $benchDriverId, int $inSeatDriverId): void
    {
        $this->assertEventNotLocked($team);

        $bench = FantasyTeamRoster::where('fantasy_team_id', $team->id)
            ->where('entity_type', 'driver')
            ->where('entity_id', $benchDriverId)
            ->where('in_seat', false)
            ->firstOrFail();

        $seated = FantasyTeamRoster::where('fantasy_team_id', $team->id)
            ->where('entity_type', 'driver')
            ->where('entity_id', $inSeatDriverId)
            ->where('in_seat', true)
            ->firstOrFail();

        DB::transaction(function () use ($bench, $seated) {
            $bench->update(['in_seat' => true]);
            $seated->update(['in_seat' => false]);
        });
    }

    /**
     * Pick up a driver or constructor from the free agent pool, dropping the specified entity from the roster.
     * The next event must not be locked.
     */
    public function pickupFreeAgent(FantasyTeam $team, League $league, string $entityType, int $pickupEntityId, int $dropEntityId): void
    {
        $this->assertEventNotLocked($team);

        $freeAgent = FreeAgentPool::where('league_id', $league->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $pickupEntityId)
            ->firstOrFail();

        $dropping = FantasyTeamRoster::where('fantasy_team_id', $team->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $dropEntityId)
            ->firstOrFail();

        // For no_duplicates leagues, ensure no other team already holds this entity.
        $rules = $league->rules ?? [];
        if ($rules['no_duplicates'] ?? false) {
            $this->assertNoDuplicateInLeague($league, $entityType, $pickupEntityId);
        }

        DB::transaction(function () use ($freeAgent, $dropping, $team, $league, $entityType, $pickupEntityId) {
            $inSeat = $dropping->in_seat;

            $dropping->delete();
            $freeAgent->delete();

            FantasyTeamRoster::create([
                'fantasy_team_id' => $team->id,
                'entity_type' => $entityType,
                'entity_id' => $pickupEntityId,
                'in_seat' => $inSeat,
                'acquired_at' => now(),
            ]);

            FreeAgentPool::create([
                'league_id' => $league->id,
                'entity_type' => $entityType,
                'entity_id' => $dropping->entity_id,
                'added_at' => now(),
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function assertEventNotLocked(FantasyTeam $team): void
    {
        $team->loadMissing('league.season');
        $seasonId = $team->league->season_id;

        $locked = Event::where('season_id', $seasonId)
            ->where('status', 'locked')
            ->exists();

        if ($locked) {
            throw new InvalidArgumentException('Roster changes are not allowed during a locked event.');
        }
    }

    protected function assertNoDuplicateInLeague(League $league, string $entityType, int $entityId): void
    {
        $alreadyOnRoster = FantasyTeamRoster::whereHas('fantasyTeam', fn ($query) => $query->where('league_id', $league->id))
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->exists();

        if ($alreadyOnRoster) {
            throw new InvalidArgumentException("This {$entityType} is already on a team in this league.");
        }
    }
}
