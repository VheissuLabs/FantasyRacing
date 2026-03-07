<?php

namespace App\Services;

use App\Models\BonusPointsScheme;
use App\Models\Event;
use App\Models\EventConstructorResult;
use App\Models\EventPitstop;
use App\Models\EventResult;
use App\Models\FantasyEventPoint;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\PointsScheme;
use App\Models\RosterSnapshot;

class PointsCalculationService
{
    /**
     * Calculate and store fantasy points on event_results (drivers) and
     * event_constructor_results (constructors) for the given event.
     */
    public function calculateForEvent(Event $event): void
    {
        $event->loadMissing('season.franchise');
        $franchiseId = $event->season->franchise_id;

        $results = EventResult::where('event_id', $event->id)->get();

        foreach ($results as $result) {
            [$points, $breakdown] = $this->driverPoints($result, $event, $franchiseId);
            $result->update([
                'fantasy_points' => $points,
                'fantasy_breakdown' => $breakdown,
            ]);
        }

        $constructorIds = $results->pluck('constructor_id')->unique();

        foreach ($constructorIds as $constructorId) {
            $constructorResults = $results->where('constructor_id', $constructorId);
            [$points, $breakdown] = $this->constructorPoints($constructorResults, $event, $franchiseId);

            EventConstructorResult::updateOrCreate(
                ['event_id' => $event->id, 'constructor_id' => $constructorId],
                [
                    'fantasy_points' => $points,
                    'fantasy_breakdown' => $breakdown,
                ],
            );
        }
    }

    /**
     * Aggregate fantasy points from event_results / event_constructor_results
     * into fantasy_event_points for all leagues in the event's season.
     */
    public function aggregateForFantasyTeams(Event $event): void
    {
        $leagues = League::where('season_id', $event->season_id)->get();

        foreach ($leagues as $league) {
            foreach ($league->fantasyTeams as $team) {
                $this->aggregateForTeam($team, $event);
            }
        }
    }

    protected function aggregateForTeam(FantasyTeam $team, Event $event): void
    {
        $snapshot = RosterSnapshot::where('event_id', $event->id)
            ->where('fantasy_team_id', $team->id)
            ->first();

        if ($snapshot) {
            $entries = collect($snapshot->snapshot);

            foreach ($entries->where('entity_type', 'driver')->where('in_seat', true) as $entry) {
                $this->storeFantasyPoints($team, $event, 'driver', $entry['entity_id']);
            }

            $constructor = $entries->firstWhere('entity_type', 'constructor');

            if ($constructor) {
                $this->storeFantasyPoints($team, $event, 'constructor', $constructor['entity_id']);
            }
        } else {
            foreach ($team->inSeatDrivers as $rosterEntry) {
                $this->storeFantasyPoints($team, $event, 'driver', $rosterEntry->entity_id);
            }

            $constructorEntry = $team->constructor();

            if ($constructorEntry) {
                $this->storeFantasyPoints($team, $event, 'constructor', $constructorEntry->entity_id);
            }
        }
    }

    protected function storeFantasyPoints(FantasyTeam $team, Event $event, string $entityType, int $entityId): void
    {
        if ($entityType === 'driver') {
            $result = EventResult::where('event_id', $event->id)
                ->where('driver_id', $entityId)
                ->first();
            $points = $result?->fantasy_points ?? 0;
            $breakdown = $result?->fantasy_breakdown ?? [];
        } else {
            $result = EventConstructorResult::where('event_id', $event->id)
                ->where('constructor_id', $entityId)
                ->first();
            $points = $result?->fantasy_points ?? 0;
            $breakdown = $result?->fantasy_breakdown ?? [];
        }

        FantasyEventPoint::updateOrCreate(
            [
                'fantasy_team_id' => $team->id,
                'event_id' => $event->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'points' => $points,
                'breakdown' => $breakdown,
                'computed_at' => now(),
            ],
        );
    }

    /**
     * @return array{0: float, 1: array<string, float>}
     */
    protected function driverPoints(EventResult $result, Event $event, int $franchiseId): array
    {
        $breakdown = [];
        $points = 0.0;

        if ($result->isClassified() && $result->finish_position) {
            $pos = (float) PointsScheme::getPointsForPosition($event->type, $result->finish_position, $franchiseId);

            if ($pos !== 0.0) {
                $points += $pos;
                $breakdown['position'] = $pos;
            }
        } elseif ($result->hasPenalty()) {
            $penaltyKey = match ($event->type) {
                'qualifying' => 'nc_dsq_penalty',
                default => 'dnf_penalty',
            };
            $penalty = $this->bonus($event->type, $penaltyKey, 'driver', $franchiseId);
            $points += $penalty;
            $breakdown[$penaltyKey] = $penalty;
        }

        if ($event->type === 'race') {
            if ($result->fastest_lap) {
                $bonusPoints = $this->bonus('race', 'fastest_lap', 'driver', $franchiseId);
                $points += $bonusPoints;
                $breakdown['fastest_lap'] = $bonusPoints;
            }
            if ($result->driver_of_the_day) {
                $bonusPoints = $this->bonus('race', 'driver_of_the_day', 'driver', $franchiseId);
                $points += $bonusPoints;
                $breakdown['driver_of_the_day'] = $bonusPoints;
            }
        } elseif ($event->type === 'sprint') {
            if ($result->fastest_lap) {
                $bonusPoints = $this->bonus('sprint', 'fastest_lap', 'driver', $franchiseId);
                $points += $bonusPoints;
                $breakdown['fastest_lap'] = $bonusPoints;
            }

            if ($result->grid_position && $result->finish_position) {
                $gained = max(0, $result->grid_position - $result->finish_position);
                $lost = max(0, $result->finish_position - $result->grid_position);

                if ($gained > 0) {
                    $perPos = $this->bonus('sprint', 'positions_gained', 'driver', $franchiseId);
                    $gainedPoints = $gained * $perPos;
                    $points += $gainedPoints;
                    $breakdown['positions_gained'] = $gainedPoints;
                }

                if ($lost > 0) {
                    $perPos = $this->bonus('sprint', 'positions_lost', 'driver', $franchiseId);
                    $lostPoints = $lost * $perPos;
                    $points += $lostPoints;
                    $breakdown['positions_lost'] = $lostPoints;
                }
            }

            if ($result->overtakes_made > 0) {
                $perOvertake = $this->bonus('sprint', 'overtake', 'driver', $franchiseId);
                $overtakePoints = $result->overtakes_made * $perOvertake;
                $points += $overtakePoints;
                $breakdown['overtakes'] = $overtakePoints;
            }
        }

        return [$points, $breakdown];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventResult>  $results
     * @return array{0: float, 1: array<string, float>}
     */
    protected function constructorPoints($results, Event $event, int $franchiseId): array
    {
        $breakdown = [];
        $points = 0.0;

        if ($event->type === 'race') {
            foreach ($results as $result) {
                if ($result->isClassified() && $result->finish_position) {
                    $pos = (float) PointsScheme::getPointsForPosition('race', $result->finish_position, $franchiseId);
                    $points += $pos;
                    $breakdown["position_{$result->driver_id}"] = $pos;
                } elseif ($result->hasPenalty()) {
                    $penalty = $this->bonus('race', 'dnf_penalty', 'driver', $franchiseId);
                    $points += $penalty;
                    $breakdown["dnf_{$result->driver_id}"] = $penalty;
                }

                if ($result->fastest_lap) {
                    $bonusPoints = $this->bonus('race', 'fastest_lap', 'driver', $franchiseId);
                    $points += $bonusPoints;
                    $breakdown["fastest_lap_{$result->driver_id}"] = $bonusPoints;
                }

                if ($result->status === 'dsq') {
                    $penalty = $this->bonus('race', 'constructor_dsq', 'constructor', $franchiseId);
                    $points += $penalty;
                    $breakdown['constructor_dsq'] = ($breakdown['constructor_dsq'] ?? 0) + $penalty;
                }
            }

            $constructorId = $results->first()->constructor_id;
            $pitstop = $this->pitstopPoints($constructorId, $event, $franchiseId);
            $points += $pitstop['total'];
            $breakdown = array_merge($breakdown, $pitstop['breakdown']);
        } elseif ($event->type === 'qualifying') {
            $q2Count = 0;
            $q3Count = 0;

            foreach ($results as $result) {
                if ($result->isClassified() && $result->finish_position) {
                    $pos = (float) PointsScheme::getPointsForPosition('qualifying', $result->finish_position, $franchiseId);
                    $points += $pos;
                    $breakdown["position_{$result->driver_id}"] = $pos;
                } elseif ($result->hasPenalty()) {
                    $penalty = $this->bonus('qualifying', 'nc_dsq_penalty', 'driver', $franchiseId);
                    $points += $penalty;
                    $breakdown["nc_dsq_{$result->driver_id}"] = $penalty;
                }

                if ($result->status === 'dsq') {
                    $penalty = $this->bonus('qualifying', 'constructor_dsq', 'constructor', $franchiseId);
                    $points += $penalty;
                    $breakdown['constructor_dsq'] = ($breakdown['constructor_dsq'] ?? 0) + $penalty;
                }

                if ($result->q2_time) {
                    $q2Count++;
                }
                if ($result->q3_time) {
                    $q3Count++;
                }
            }

            $qStage = $this->qStageBonus($q2Count, $q3Count, $franchiseId);

            if ($qStage['points'] !== 0.0) {
                $points += $qStage['points'];
                $breakdown[$qStage['key']] = $qStage['points'];
            }
        } elseif ($event->type === 'sprint') {
            foreach ($results as $result) {
                if ($result->isClassified() && $result->finish_position) {
                    $pos = (float) PointsScheme::getPointsForPosition('sprint', $result->finish_position, $franchiseId);
                    $points += $pos;
                    $breakdown["position_{$result->driver_id}"] = $pos;
                } elseif ($result->hasPenalty()) {
                    $penalty = $this->bonus('sprint', 'dnf_penalty', 'driver', $franchiseId);
                    $points += $penalty;
                    $breakdown["dnf_{$result->driver_id}"] = $penalty;
                }

                if ($result->fastest_lap) {
                    $bonusPoints = $this->bonus('sprint', 'fastest_lap', 'driver', $franchiseId);
                    $points += $bonusPoints;
                    $breakdown["fastest_lap_{$result->driver_id}"] = $bonusPoints;
                }

                if ($result->grid_position && $result->finish_position) {
                    $gained = max(0, $result->grid_position - $result->finish_position);
                    $lost = max(0, $result->finish_position - $result->grid_position);

                    if ($gained > 0) {
                        $bonusPoints = $gained * $this->bonus('sprint', 'positions_gained', 'driver', $franchiseId);
                        $points += $bonusPoints;
                        $breakdown["positions_gained_{$result->driver_id}"] = $bonusPoints;
                    }

                    if ($lost > 0) {
                        $bonusPoints = $lost * $this->bonus('sprint', 'positions_lost', 'driver', $franchiseId);
                        $points += $bonusPoints;
                        $breakdown["positions_lost_{$result->driver_id}"] = $bonusPoints;
                    }
                }

                if ($result->overtakes_made > 0) {
                    $bonusPoints = $result->overtakes_made * $this->bonus('sprint', 'overtake', 'driver', $franchiseId);
                    $points += $bonusPoints;
                    $breakdown["overtakes_{$result->driver_id}"] = $bonusPoints;
                }

                if ($result->status === 'dsq') {
                    $penalty = $this->bonus('sprint', 'constructor_dsq', 'constructor', $franchiseId);
                    $points += $penalty;
                    $breakdown['constructor_dsq'] = ($breakdown['constructor_dsq'] ?? 0) + $penalty;
                }
            }
        }

        return [$points, $breakdown];
    }

    /**
     * @return array{total: float, breakdown: array<string, float>}
     */
    protected function pitstopPoints(int $constructorId, Event $event, int $franchiseId): array
    {
        $pitstops = EventPitstop::where('event_id', $event->id)
            ->where('constructor_id', $constructorId)
            ->get();

        if ($pitstops->isEmpty()) {
            return ['total' => 0.0, 'breakdown' => []];
        }

        $breakdown = [];
        $total = 0.0;

        $fastest = $pitstops->sortBy('stop_time_seconds')->first();
        $stopTime = (float) $fastest->stop_time_seconds;

        $bracketKey = null;

        if ($stopTime < 2.0) {
            $bracketKey = 'pitstop_under_2s';
        } elseif ($stopTime < 2.2) {
            $bracketKey = 'pitstop_2s_2.19s';
        } elseif ($stopTime < 2.5) {
            $bracketKey = 'pitstop_2.2s_2.49s';
        } elseif ($stopTime < 3.0) {
            $bracketKey = 'pitstop_2.5s_2.99s';
        }

        if ($bracketKey) {
            $bonusPoints = $this->bonus('race', $bracketKey, 'constructor', $franchiseId);
            $total += $bonusPoints;
            $breakdown[$bracketKey] = $bonusPoints;
        }

        if ($fastest->is_fastest_of_event) {
            $bonusPoints = $this->bonus('race', 'pitstop_fastest', 'constructor', $franchiseId);
            $total += $bonusPoints;
            $breakdown['pitstop_fastest'] = $bonusPoints;
        }

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    /**
     * @return array{key: string, points: float}
     */
    protected function qStageBonus(int $q2Count, int $q3Count, int $franchiseId): array
    {
        if ($q3Count >= 2) {
            return ['key' => 'constructor_both_reaches_q3', 'points' => $this->bonus('qualifying', 'constructor_both_reaches_q3', 'constructor', $franchiseId)];
        }

        if ($q3Count === 1) {
            return ['key' => 'constructor_one_reaches_q3', 'points' => $this->bonus('qualifying', 'constructor_one_reaches_q3', 'constructor', $franchiseId)];
        }

        if ($q2Count >= 2) {
            return ['key' => 'constructor_both_reaches_q2', 'points' => $this->bonus('qualifying', 'constructor_both_reaches_q2', 'constructor', $franchiseId)];
        }

        if ($q2Count === 1) {
            return ['key' => 'constructor_one_reaches_q2', 'points' => $this->bonus('qualifying', 'constructor_one_reaches_q2', 'constructor', $franchiseId)];
        }

        return ['key' => 'constructor_neither_reaches_q2', 'points' => $this->bonus('qualifying', 'constructor_neither_reaches_q2', 'constructor', $franchiseId)];
    }

    protected function bonus(string $eventType, string $bonusKey, string $appliesTo, int $franchiseId): float
    {
        return (float) BonusPointsScheme::getBonusPoints($eventType, $bonusKey, $appliesTo, $franchiseId);
    }
}
