<?php

namespace App\Filament;

use App\Models\Season;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class GlobalSeasonSelector extends Component
{
    public ?int $seasonId = null;

    public function mount(): void
    {
        $this->seasonId = session('filament_season_id')
            ?? Season::where('is_active', true)->value('id');

        if ($this->seasonId) {
            session(['filament_season_id' => $this->seasonId]);
        }
    }

    public function updatedSeasonId(?int $value): void
    {
        session(['filament_season_id' => $value]);

        $this->redirect(request()->header('Referer', '/cp'), navigate: true);
    }

    public function getSeasons(): Collection
    {
        return Season::when(
            GlobalFranchiseSelector::getCurrentFranchiseId(),
            fn ($query, $franchiseId) => $query->where('franchise_id', $franchiseId),
        )->orderByDesc('year')->pluck('name', 'id');
    }

    /**
     * Retrieve the current season ID from the session, falling back to the active season.
     */
    public static function getCurrentSeasonId(): ?int
    {
        return session('filament_season_id')
            ?? Season::where('is_active', true)->value('id');
    }

    public function render(): View
    {
        return view('filament.global-season-selector', [
            'seasons' => $this->getSeasons(),
        ]);
    }
}
