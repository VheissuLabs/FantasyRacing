<?php

use App\Http\Controllers\Api\V1\LeagueApiController;
use App\Http\Controllers\Api\V1\StandingsApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/leagues', [LeagueApiController::class, 'index']);
    Route::get('/leagues/{league:slug}', [LeagueApiController::class, 'show']);
    Route::get('/leagues/{league:slug}/teams', [LeagueApiController::class, 'teams']);
    Route::get('/leagues/{league:slug}/standings', [StandingsApiController::class, 'show']);
});
