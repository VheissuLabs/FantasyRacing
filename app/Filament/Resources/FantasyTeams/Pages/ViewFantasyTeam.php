<?php

namespace App\Filament\Resources\FantasyTeams\Pages;

use App\Filament\Resources\FantasyTeams\FantasyTeamResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFantasyTeam extends ViewRecord
{
    protected static string $resource = FantasyTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
