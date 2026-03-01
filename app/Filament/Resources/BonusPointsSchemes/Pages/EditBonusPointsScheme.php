<?php

namespace App\Filament\Resources\BonusPointsSchemes\Pages;

use App\Filament\Resources\BonusPointsSchemes\BonusPointsSchemeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBonusPointsScheme extends EditRecord
{
    protected static string $resource = BonusPointsSchemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
