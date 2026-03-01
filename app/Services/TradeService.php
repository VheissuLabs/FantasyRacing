<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamRoster;
use App\Models\FreeAgentPool;
use App\Models\League;
use App\Models\Trade;
use App\Models\TradeItem;
use App\Notifications\TradeReceivedNotification;
use App\Notifications\TradeResolvedNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TradeService
{
    /**
     * Initiate a trade proposal between two teams (or from free agent pool).
     * If the league does not require approval, the trade is executed immediately.
     *
     * @param  array<int, array{entity_type: string, entity_id: int}>  $giving  Items the initiator gives up.
     * @param  array<int, array{entity_type: string, entity_id: int}>  $receiving  Items the initiator receives.
     */
    public function propose(
        League $league,
        FantasyTeam $initiator,
        ?FantasyTeam $receiver,
        array $giving,
        array $receiving,
    ): Trade {
        $this->assertNoActiveEventLock($league, $giving, $receiving);

        $requiresApproval = $league->tradeApprovalRequired();

        $trade = DB::transaction(function () use ($league, $initiator, $receiver, $giving, $receiving, $requiresApproval) {
            $trade = Trade::create([
                'league_id' => $league->id,
                'initiator_team_id' => $initiator->id,
                'receiver_team_id' => $receiver?->id,
                'status' => $requiresApproval ? 'pending' : 'completed',
                'initiated_at' => now(),
                'resolved_at' => $requiresApproval ? null : now(),
            ]);

            foreach ($giving as $item) {
                TradeItem::create([
                    'trade_id' => $trade->id,
                    'from_team_id' => $initiator->id,
                    'to_team_id' => $receiver?->id,
                    'entity_type' => $item['entity_type'],
                    'entity_id' => $item['entity_id'],
                ]);
            }

            foreach ($receiving as $item) {
                TradeItem::create([
                    'trade_id' => $trade->id,
                    'from_team_id' => $receiver?->id,
                    'to_team_id' => $initiator->id,
                    'entity_type' => $item['entity_type'],
                    'entity_id' => $item['entity_id'],
                ]);
            }

            if (! $requiresApproval) {
                $this->executeTransfers($trade);
            }

            return $trade;
        });

        // Notify receiver about the pending offer
        if ($requiresApproval && $receiver?->user) {
            $receiver->user->notify(new TradeReceivedNotification($trade));
        }

        return $trade;
    }

    /**
     * Accept a pending trade and execute the roster transfers.
     */
    public function accept(Trade $trade): void
    {
        if (! $trade->isPending()) {
            throw new InvalidArgumentException('Trade is not pending.');
        }

        $league = $trade->league;
        $items = $trade->items->toArray();

        $giving = array_filter($items, fn ($item) => $item['from_team_id'] === $trade->initiator_team_id);
        $receiving = array_filter($items, fn ($item) => $item['to_team_id'] === $trade->initiator_team_id);

        $this->assertNoActiveEventLock($league, array_values($giving), array_values($receiving));

        if ($league->noDuplicates() && $trade->receiver_team_id) {
            $this->assertNoDuplicatesPostTrade($trade);
        }

        DB::transaction(function () use ($trade) {
            $this->executeTransfers($trade);
            $trade->update(['status' => 'completed', 'resolved_at' => now()]);
        });

        $trade->initiatorTeam->user?->notify(new TradeResolvedNotification($trade->fresh()));
    }

    /**
     * Reject or cancel a pending trade.
     */
    public function reject(Trade $trade): void
    {
        if (! $trade->isPending()) {
            throw new InvalidArgumentException('Trade is not pending.');
        }

        $trade->update(['status' => 'rejected', 'resolved_at' => now()]);

        $trade->initiatorTeam->user?->notify(new TradeResolvedNotification($trade->fresh()));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function executeTransfers(Trade $trade): void
    {
        foreach ($trade->items as $item) {
            // Remove from sender
            if ($item->from_team_id) {
                FantasyTeamRoster::where('fantasy_team_id', $item->from_team_id)
                    ->where('entity_type', $item->entity_type)
                    ->where('entity_id', $item->entity_id)
                    ->delete();

                FreeAgentPool::where('league_id', $trade->league_id)
                    ->where('entity_type', $item->entity_type)
                    ->where('entity_id', $item->entity_id)
                    ->delete();
            } else {
                // Coming from free agent pool
                FreeAgentPool::where('league_id', $trade->league_id)
                    ->where('entity_type', $item->entity_type)
                    ->where('entity_id', $item->entity_id)
                    ->delete();
            }

            // Add to recipient
            if ($item->to_team_id) {
                $inSeat = $item->entity_type === 'constructor'
                    || FantasyTeamRoster::where('fantasy_team_id', $item->to_team_id)
                        ->where('entity_type', 'driver')
                        ->where('in_seat', true)
                        ->count() < 2;

                FantasyTeamRoster::updateOrCreate(
                    [
                        'fantasy_team_id' => $item->to_team_id,
                        'entity_type' => $item->entity_type,
                        'entity_id' => $item->entity_id,
                    ],
                    ['in_seat' => $inSeat, 'acquired_at' => now()],
                );
            } else {
                FreeAgentPool::updateOrCreate(
                    ['league_id' => $trade->league_id, 'entity_type' => $item->entity_type, 'entity_id' => $item->entity_id],
                    ['added_at' => now()],
                );
            }
        }
    }

    /**
     * @param  array<int, array{entity_type: string, entity_id: int}>  $giving
     * @param  array<int, array{entity_type: string, entity_id: int}>  $receiving
     */
    protected function assertNoActiveEventLock(League $league, array $giving, array $receiving): void
    {
        $lockedEvent = Event::where('season_id', $league->season_id)
            ->where('status', 'locked')
            ->exists();

        if (! $lockedEvent) {
            return;
        }

        $allEntityIds = array_merge(
            array_column($giving, 'entity_id'),
            array_column($receiving, 'entity_id'),
        );

        if (! empty($allEntityIds)) {
            throw new InvalidArgumentException('Trades involving entities from an active locked event are blocked until the event concludes.');
        }
    }

    protected function assertNoDuplicatesPostTrade(Trade $trade): void
    {
        foreach ($trade->items as $item) {
            if (! $item->to_team_id) {
                continue;
            }

            $alreadyOnTeam = FantasyTeamRoster::where('fantasy_team_id', $item->to_team_id)
                ->where('entity_type', $item->entity_type)
                ->where('entity_id', $item->entity_id)
                ->exists();

            if ($alreadyOnTeam) {
                throw new InvalidArgumentException("Team already has this {$item->entity_type} on their roster.");
            }
        }
    }
}
