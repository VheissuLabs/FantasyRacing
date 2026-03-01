<?php

namespace App\Filament\Resources\BonusPointsSchemes\Pages;

use App\Filament\Resources\BonusPointsSchemes\BonusPointsSchemeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBonusPointsSchemes extends ListRecords
{
    protected static string $resource = BonusPointsSchemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
