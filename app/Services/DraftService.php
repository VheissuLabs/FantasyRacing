<?php

namespace App\Services;

use App\Events\Draft\DraftCompleted;
use App\Events\Draft\DraftOrderUpdated;
use App\Events\Draft\DraftPaused;
use App\Events\Draft\DraftRestarted;
use App\Events\Draft\DraftResumed;
use App\Events\Draft\DraftStarted;
use App\Events\Draft\PickMade;
use App\Events\Draft\PickTurnStarted;
use App\Jobs\ScheduleAutoPick;
use App\Models\Constructor;
use App\Models\DraftOrder;
use App\Models\DraftPick;
use App\Models\DraftSession;
use App\Models\Driver;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamRoster;
use App\Models\FreeAgentPool;
use App\Models\League;
use App\Models\SeasonConstructor;
use App\Models\SeasonDriver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DraftService
{
    /**
     * Transition a session from pending to active and broadcast DraftStarted.
     */
    public function start(DraftSession $session): void
    {
        if ($session->orders()->count() === 0) {
            throw new RuntimeException('Draft order has not been generated yet.');
        }

        $session->update([
            'status' => 'active',
            'started_at' => now(),
            'current_pick_number' => 1,
        ]);

        $session->orders()->where('pick_number', 1)->update(['status' => 'active']);

        $pickOrder = $session->orders->map(fn ($order) => [
            'pick_number' => $order->pick_number,
            'team_id' => $order->fantasy_team_id,
            'round' => $order->round,
        ])->toArray();

        broadcast(new DraftStarted($session, $pickOrder));

        $firstOrder = $session->orders->first();
        $timerExpiresAt = $this->scheduleAutoPickIfTimed($session);

        broadcast(new PickTurnStarted(
            $session,
            $firstOrder->pick_number,
            $firstOrder->fantasy_team_id,
            $timerExpiresAt,
        ));
    }

    /**
     * Randomize the first-round team order, then regenerate all draft orders.
     */
    public function randomizeOrder(DraftSession $session): void
    {
        $session->loadMissing('league.fantasyTeams');

        $teams = $session->league->fantasyTeams()->inRandomOrder()->pluck('id')->toArray();

        $this->generateOrderWithTeamSequence($session, $teams);
    }

    /**
     * Set a specific first-round team order, then regenerate all draft orders.
     *
     * @param  list<int>  $teamIds
     */
    public function reorderTeams(DraftSession $session, array $teamIds): void
    {
        $session->loadMissing('league.fantasyTeams');

        $existingIds = $session->league->fantasyTeams()->pluck('id')->sort()->values()->toArray();
        $providedIds = collect($teamIds)->sort()->values()->toArray();

        if ($existingIds !== $providedIds) {
            throw new InvalidArgumentException('Provided team IDs do not match the league teams.');
        }

        $this->generateOrderWithTeamSequence($session, $teamIds);

        broadcast(new DraftOrderUpdated($session));
    }

    /**
     * Validate and record a pick, advance current_pick_number, broadcast, and schedule auto-pick.
     *
     * @throws InvalidArgumentException
     */
    public function makePick(DraftSession $session, FantasyTeam $team, string $entityType, int $entityId, bool $isAutoPick = false): DraftPick
    {
        if (! $session->isActive()) {
            throw new InvalidArgumentException('Draft is not active.');
        }

        $currentOrder = $session->currentOrder();

        if (! $currentOrder) {
            throw new InvalidArgumentException('No current pick slot found.');
        }

        if ($currentOrder->fantasy_team_id !== $team->id) {
            throw new InvalidArgumentException('It is not this team\'s turn.');
        }

        if ($currentOrder->entity_type_restriction !== $entityType) {
            throw new InvalidArgumentException("This pick slot requires entity type '{$currentOrder->entity_type_restriction}'.");
        }

        $this->assertEntityAvailable($session, $entityType, $entityId);

        return DB::transaction(function () use ($session, $team, $currentOrder, $entityType, $entityId, $isAutoPick) {
            $pick = DraftPick::create([
                'draft_session_id' => $session->id,
                'draft_order_id' => $currentOrder->id,
                'fantasy_team_id' => $team->id,
                'pick_number' => $session->current_pick_number,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'is_auto_pick' => $isAutoPick,
                'picked_at' => now(),
            ]);

            $currentOrder->update(['status' => 'completed']);

            $nextPickNumber = $session->current_pick_number + 1;
            $isLastPick = $nextPickNumber > $session->total_picks;

            if (! $isLastPick) {
                $session->orders()->where('pick_number', $nextPickNumber)->update(['status' => 'active']);
            }

            $session->update(['current_pick_number' => $nextPickNumber]);

            if ($isLastPick) {
                $this->completeDraft($session);
            } else {
                $this->broadcastPickMadeAndStartNextTurn($session, $pick, $entityType, $entityId);
            }

            return $pick;
        });
    }

    /**
     * Auto-pick the first available entity for the current pick slot.
     */
    public function autoPick(DraftSession $session): DraftPick
    {
        $session->refresh();
        $currentOrder = $session->currentOrder();

        if (! $currentOrder) {
            throw new RuntimeException('No current pick slot for auto-pick.');
        }

        $team = $currentOrder->fantasyTeam;
        $entityType = $currentOrder->entity_type_restriction;

        $entityId = $entityType === 'constructor'
            ? $this->firstAvailableConstructor($session)
            : $this->firstAvailableDriver($session);

        if (! $entityId) {
            throw new RuntimeException("No available {$entityType} for auto-pick.");
        }

        return $this->makePick($session, $team, $entityType, $entityId, isAutoPick: true);
    }

    /**
     * Create fantasy_team_roster rows from picks, seed the free agent pool, broadcast completion.
     */
    public function completeDraft(DraftSession $session): void
    {
        $session->loadMissing('league');
        $league = $session->league;

        DB::transaction(function () use ($session, $league) {
            // Build rosters from all picks
            foreach ($session->picks as $pick) {
                FantasyTeamRoster::updateOrCreate(
                    [
                        'fantasy_team_id' => $pick->fantasy_team_id,
                        'entity_type' => $pick->entity_type,
                        'entity_id' => $pick->entity_id,
                    ],
                    [
                        'in_seat' => $pick->entity_type === 'constructor' ? true : $this->determineInSeat($pick->fantasy_team_id, $pick->entity_type),
                        'acquired_at' => $pick->picked_at,
                    ],
                );
            }

            // Seed free agent pool with unpicked entities
            $this->seedFreeAgentPool($session, $league);

            $completedAt = now();
            $session->update(['status' => 'completed', 'completed_at' => $completedAt]);
            $league->update(['draft_completed_at' => $completedAt]);
        });

        broadcast(new DraftCompleted($session, now()->toISOString()));
    }

    public function pause(DraftSession $session, User $by): void
    {
        if (! $session->isActive()) {
            throw new InvalidArgumentException('Draft is not active.');
        }

        $session->update(['status' => 'paused', 'paused_by' => $by->id]);

        broadcast(new DraftPaused($session, $by->id, now()->toISOString()));
    }

    public function resume(DraftSession $session): void
    {
        if (! $session->isPaused()) {
            throw new InvalidArgumentException('Draft is not paused.');
        }

        $session->update(['status' => 'active', 'paused_by' => null]);

        $currentOrder = $session->currentOrder();
        $timerExpiresAt = $this->scheduleAutoPickIfTimed($session);

        broadcast(new DraftResumed(
            $session,
            $session->current_pick_number,
            $currentOrder?->fantasy_team_id ?? 0,
            $timerExpiresAt,
        ));
    }

    /**
     * Reset a draft back to pending state, clearing all picks and related data.
     */
    public function restart(DraftSession $session): void
    {
        $session->loadMissing('league');
        $league = $session->league;
        $wasCompleted = $session->isCompleted();

        DB::transaction(function () use ($session, $league, $wasCompleted) {
            $session->picks()->delete();
            $session->orders()->update(['status' => 'pending']);

            $session->update([
                'status' => 'pending',
                'current_pick_number' => 1,
                'started_at' => null,
                'completed_at' => null,
                'paused_by' => null,
            ]);

            if ($wasCompleted) {
                $league->update(['draft_completed_at' => null]);

                $teamIds = $league->fantasyTeams()->pluck('id');
                FantasyTeamRoster::whereIn('fantasy_team_id', $teamIds)->delete();
                FreeAgentPool::where('league_id', $league->id)->delete();
            }
        });

        broadcast(new DraftRestarted($session));
    }

    /**
     * Build draft order rows from a given first-round team sequence.
     *
     * @param  list<int>  $teams
     */
    protected function generateOrderWithTeamSequence(DraftSession $session, array $teams): void
    {
        if (count($teams) === 0) {
            throw new RuntimeException('Cannot generate draft order: league has no fantasy teams.');
        }

        $totalRounds = 4;
        $pickNumber = 1;
        $rows = [];

        for ($round = 1; $round <= $totalRounds; $round++) {
            $roundTeams = ($session->type === 'snake' && $round % 2 === 0)
                ? array_reverse($teams)
                : $teams;

            foreach ($roundTeams as $position => $teamId) {
                $rows[] = [
                    'draft_session_id' => $session->id,
                    'pick_number' => $pickNumber,
                    'round' => $round,
                    'round_pick' => $position + 1,
                    'fantasy_team_id' => $teamId,
                    'entity_type_restriction' => $round === 1 ? 'constructor' : 'driver',
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $pickNumber++;
            }
        }

        DraftOrder::where('draft_session_id', $session->id)->delete();
        DraftOrder::insert($rows);

        $session->update([
            'total_picks' => $pickNumber - 1,
            'current_pick_number' => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function assertEntityAvailable(DraftSession $session, string $entityType, int $entityId): void
    {
        $rules = $session->league->rules ?? [];
        $noDuplicates = $rules['no_duplicates'] ?? false;

        if (! $noDuplicates) {
            return;
        }

        $alreadyPicked = DraftPick::where('draft_session_id', $session->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->exists();

        if ($alreadyPicked) {
            throw new InvalidArgumentException("This {$entityType} has already been drafted.");
        }
    }

    protected function broadcastPickMadeAndStartNextTurn(DraftSession $session, DraftPick $pick, string $entityType, int $entityId): void
    {
        $entity = $entityType === 'driver'
            ? Driver::find($entityId)
            : Constructor::find($entityId);

        $nextOrder = DraftOrder::where('draft_session_id', $session->id)
            ->where('pick_number', $session->current_pick_number)
            ->first();

        $timerExpiresAt = $this->scheduleAutoPickIfTimed($session);

        broadcast(new PickMade(
            $session,
            $pick,
            $entity?->name ?? '',
            $nextOrder?->pick_number,
            $nextOrder?->fantasy_team_id,
            $timerExpiresAt,
        ));

        if ($nextOrder) {
            broadcast(new PickTurnStarted(
                $session,
                $nextOrder->pick_number,
                $nextOrder->fantasy_team_id,
                $timerExpiresAt,
            ));
        }
    }

    protected function scheduleAutoPickIfTimed(DraftSession $session): ?string
    {
        if (! $session->pick_time_limit_seconds) {
            return null;
        }

        $expiresAt = now()->addSeconds($session->pick_time_limit_seconds);

        ScheduleAutoPick::dispatch($session->id, $session->current_pick_number)
            ->delay($expiresAt);

        return $expiresAt->toISOString();
    }

    protected function firstAvailableConstructor(DraftSession $session): ?int
    {
        $seasonId = $session->league->season_id;
        $pickedIds = DraftPick::where('draft_session_id', $session->id)
            ->where('entity_type', 'constructor')
            ->pluck('entity_id');

        return SeasonConstructor::where('season_id', $seasonId)
            ->whereNotIn('constructor_id', $pickedIds)
            ->join('constructors', 'constructors.id', '=', 'season_constructors.constructor_id')
            ->orderBy('constructors.name')
            ->value('constructor_id');
    }

    protected function firstAvailableDriver(DraftSession $session): ?int
    {
        $seasonId = $session->league->season_id;
        $pickedIds = DraftPick::where('draft_session_id', $session->id)
            ->where('entity_type', 'driver')
            ->pluck('entity_id');

        return SeasonDriver::where('season_id', $seasonId)
            ->whereNull('effective_to')
            ->whereNotIn('driver_id', $pickedIds)
            ->join('drivers', 'drivers.id', '=', 'season_drivers.driver_id')
            ->orderBy('drivers.name')
            ->value('driver_id');
    }

    /**
     * Determine in_seat for the nth driver pick for a team.
     * First 2 drivers are in-seat, 3rd is bench.
     */
    protected function determineInSeat(int $fantasyTeamId, string $entityType): bool
    {
        if ($entityType !== 'driver') {
            return true;
        }

        $existingDriverCount = FantasyTeamRoster::where('fantasy_team_id', $fantasyTeamId)
            ->where('entity_type', 'driver')
            ->count();

        return $existingDriverCount < 2;
    }

    protected function seedFreeAgentPool(DraftSession $session, League $league): void
    {
        $seasonId = $league->season_id;

        $pickedConstructorIds = DraftPick::where('draft_session_id', $session->id)
            ->where('entity_type', 'constructor')
            ->pluck('entity_id');

        $pickedDriverIds = DraftPick::where('draft_session_id', $session->id)
            ->where('entity_type', 'driver')
            ->pluck('entity_id');

        $unpickedConstructors = SeasonConstructor::where('season_id', $seasonId)
            ->whereNotIn('constructor_id', $pickedConstructorIds)
            ->pluck('constructor_id');

        $unpickedDrivers = SeasonDriver::where('season_id', $seasonId)
            ->whereNull('effective_to')
            ->whereNotIn('driver_id', $pickedDriverIds)
            ->pluck('driver_id');

        $now = now();

        foreach ($unpickedConstructors as $constructorId) {
            FreeAgentPool::updateOrCreate(
                ['league_id' => $league->id, 'entity_type' => 'constructor', 'entity_id' => $constructorId],
                ['added_at' => $now],
            );
        }

        foreach ($unpickedDrivers as $driverId) {
            FreeAgentPool::updateOrCreate(
                ['league_id' => $league->id, 'entity_type' => 'driver', 'entity_id' => $driverId],
                ['added_at' => $now],
            );
        }
    }
}
