<?php

namespace App\Filament\Resources\PointsSchemes\Pages;

use App\Filament\Resources\PointsSchemes\PointsSchemeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPointsSchemes extends ListRecords
{
    protected static string $resource = PointsSchemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
