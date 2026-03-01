<?php

namespace App\Policies;

use App\Models\FantasyTeam;
use App\Models\User;

class FantasyTeamPolicy
{
    /**
     * Super admins bypass all policy checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * League members can view any team within their league.
     */
    public function view(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $fantasyTeam->league->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Only the team owner can update their team name.
     */
    public function update(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $fantasyTeam->user_id === $user->id;
    }

    /**
     * Only the team owner can manage their roster (bench swap, free agent pickup).
     */
    public function manageRoster(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $fantasyTeam->user_id === $user->id;
    }

    /**
     * Only the team owner can initiate trades from their team.
     */
    public function initiateTrade(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $fantasyTeam->user_id === $user->id;
    }

    /**
     * The team owner or commissioner can delete a team.
     */
    public function delete(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $fantasyTeam->user_id === $user->id
            || $fantasyTeam->league->isCommissioner($user);
    }
}
