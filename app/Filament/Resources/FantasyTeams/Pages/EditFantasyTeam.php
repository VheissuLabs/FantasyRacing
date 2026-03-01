<?php

namespace App\Filament\Resources\FantasyTeams\Pages;

use App\Filament\Resources\FantasyTeams\FantasyTeamResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFantasyTeam extends EditRecord
{
    protected static string $resource = FantasyTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
