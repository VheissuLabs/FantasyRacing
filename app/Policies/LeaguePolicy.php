<?php

namespace App\Policies;

use App\Models\League;
use App\Models\User;

class LeaguePolicy
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
     * Any authenticated user can view public leagues. Private leagues require membership.
     */
    public function view(User $user, League $league): bool
    {
        if ($league->visibility === 'public') {
            return true;
        }

        return $league->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Only commissioners can update league settings.
     */
    public function update(User $user, League $league): bool
    {
        return $league->isCommissioner($user);
    }

    /**
     * Only commissioners can delete a league.
     */
    public function delete(User $user, League $league): bool
    {
        return $league->isCommissioner($user);
    }

    /**
     * Only commissioners can manage invites (send, revoke, resend).
     */
    public function manageInvites(User $user, League $league): bool
    {
        return $league->isCommissioner($user);
    }

    /**
     * Only commissioners can manage join requests (approve, reject).
     */
    public function manageJoinRequests(User $user, League $league): bool
    {
        return $league->isCommissioner($user);
    }

    /**
     * A user can join if the league uses an open or request join policy,
     * is not full, and the user is not already a member.
     */
    public function join(User $user, League $league): bool
    {
        if ($league->isFull()) {
            return false;
        }

        if ($league->members()->where('user_id', $user->id)->exists()) {
            return false;
        }

        return in_array($league->join_policy, ['open', 'request'], strict: true);
    }

    /**
     * League members can view member lists and other member-only details.
     */
    public function viewAsMember(User $user, League $league): bool
    {
        return $league->members()->where('user_id', $user->id)->exists();
    }
}
