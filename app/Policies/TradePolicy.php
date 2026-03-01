<?php

namespace App\Policies;

use App\Models\Trade;
use App\Models\User;

class TradePolicy
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
     * League members can view trades within their league.
     */
    public function view(User $user, Trade $trade): bool
    {
        return $trade->league->members()->where('user_id', $user->id)->exists();
    }

    /**
     * The receiving team owner or commissioner can accept a pending trade.
     */
    public function accept(User $user, Trade $trade): bool
    {
        if (! $trade->isPending()) {
            return false;
        }

        $isReceiver = $trade->receiverTeam?->user_id === $user->id;
        $isCommissioner = $trade->league->isCommissioner($user);

        return $isReceiver || $isCommissioner;
    }

    /**
     * The initiator, receiver, or commissioner can reject a pending trade.
     */
    public function reject(User $user, Trade $trade): bool
    {
        if (! $trade->isPending()) {
            return false;
        }

        $isInitiator = $trade->initiatorTeam->user_id === $user->id;
        $isReceiver = $trade->receiverTeam?->user_id === $user->id;
        $isCommissioner = $trade->league->isCommissioner($user);

        return $isInitiator || $isReceiver || $isCommissioner;
    }
}
