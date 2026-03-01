<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncUserTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $timezone = $request->header('X-Timezone');

        if ($user && $timezone && $timezone !== $user->timezone && in_array($timezone, timezone_identifiers_list())) {
            $user->updateQuietly(['timezone' => $timezone]);
        }

        return $next($request);
    }
}
