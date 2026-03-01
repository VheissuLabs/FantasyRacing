<?php

namespace App\Filament;

use App\Models\Franchise;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GlobalFranchiseSelector extends Component
{
    public ?int $franchiseId = null;

    public function mount(): void
    {
        $this->franchiseId = session('filament_franchise_id');

        if (! $this->franchiseId || ! $this->getFranchises()->has($this->franchiseId)) {
            $this->franchiseId = $this->getFranchises()->keys()->first();
        }

        if ($this->franchiseId) {
            session(['filament_franchise_id' => $this->franchiseId]);
        }
    }

    public function updatedFranchiseId(?int $value): void
    {
        if ($value && ! $this->getFranchises()->has($value)) {
            return;
        }

        session([
            'filament_franchise_id' => $value,
            'filament_season_id' => null,
        ]);

        $this->redirect(request()->header('Referer', '/cp'), navigate: true);
    }

    public function getFranchises(): Collection
    {
        $user = Auth::user();

        if ($user?->isSuperAdmin()) {
            return Franchise::orderBy('name')->pluck('name', 'id');
        }

        return $user?->managedFranchises()->orderBy('name')->pluck('name', 'franchises.id') ?? collect();
    }

    public static function getCurrentFranchiseId(): ?int
    {
        return session('filament_franchise_id')
            ?? Franchise::where('is_active', true)->value('id');
    }

    public function render(): View
    {
        return view('filament.global-franchise-selector', [
            'franchises' => $this->getFranchises(),
        ]);
    }
}
