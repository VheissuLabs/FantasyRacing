<?php

use App\Models\DraftSession;
use App\Models\Event;
use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

Broadcast::channel('draft.{draftSession}', function (User $user, DraftSession $draftSession) {
    if ($draftSession->league->members()->where('user_id', $user->id)->exists()) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    return false;
});

Broadcast::channel('league.{league}', function (User $user, League $league) {
    if ($league->members()->where('user_id', $user->id)->exists()) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    return false;
});

Broadcast::channel('event.{event}', function (User $user, Event $event) {
    return $user->hasVerifiedEmail();
});
