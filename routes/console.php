<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expire pending invites that have passed their expiry date (runs hourly)
Schedule::command('invites:expire')->hourly();

// Notify teams about drafts starting soon (runs every 15 minutes)
Schedule::command('draft:notify-starting', ['--minutes' => 30])->everyFifteenMinutes();

// Recalculate any completed events that are missing fantasy points (runs nightly)
Schedule::command('points:calculate', ['--all-pending' => true])->dailyAt('03:00');
