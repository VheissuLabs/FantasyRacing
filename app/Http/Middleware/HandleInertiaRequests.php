<?php

namespace App\Http\Middleware;

use App\Models\Franchise;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $franchiseSlug = $request->cookie('franchise', '');
        $seasonId = $request->cookie('season_id');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'timezone' => $request->user()?->timezone ?? $request->header('X-Timezone', 'UTC'),
            'globalFilters' => [
                'franchise' => $franchiseSlug ?: null,
                'seasonId' => $seasonId ? (int) $seasonId : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shareOnce(Request $request): array
    {
        return array_merge(parent::shareOnce($request), [
            'franchises' => fn () => Franchise::query()
                ->where('is_active', true)
                ->with(['seasons' => fn ($query) => $query->orderByDesc('year')])
                ->orderBy('name')
                ->get()
                ->map(fn (Franchise $franchise) => [
                    'id' => $franchise->id,
                    'name' => $franchise->name,
                    'slug' => $franchise->slug,
                    'seasons' => $franchise->seasons->map(fn ($season) => [
                        'id' => $season->id,
                        'name' => $season->name,
                        'year' => $season->year,
                    ]),
                ]),
        ]);
    }
}
