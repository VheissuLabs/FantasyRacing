<?php

namespace App\Filament\Resources\Constructors\Pages;

use App\Filament\Resources\Constructors\ConstructorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConstructor extends EditRecord
{
    protected static string $resource = ConstructorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
