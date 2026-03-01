<?php

namespace App\Filament\Resources\FantasyTeams\Pages;

use App\Filament\Resources\FantasyTeams\FantasyTeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFantasyTeams extends ListRecords
{
    protected static string $resource = FantasyTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
