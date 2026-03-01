<?php

namespace App\Filament\Resources\PointsSchemes\Pages;

use App\Filament\Resources\PointsSchemes\PointsSchemeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPointsScheme extends EditRecord
{
    protected static string $resource = PointsSchemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
