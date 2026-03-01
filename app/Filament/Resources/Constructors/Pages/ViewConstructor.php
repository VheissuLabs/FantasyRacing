<?php

namespace App\Filament\Resources\Constructors\Pages;

use App\Filament\Resources\Constructors\ConstructorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConstructor extends ViewRecord
{
    protected static string $resource = ConstructorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
