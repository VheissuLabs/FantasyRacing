<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateUserTimezone
{
    public function handle(Login $event): void
    {
        $timezone = request()->input('timezone');

        if ($timezone && in_array($timezone, timezone_identifiers_list())) {
            $event->user->update(['timezone' => $timezone]);
        }
    }
}
