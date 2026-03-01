<?php

namespace App\Filament\Resources\Constructors\Pages;

use App\Filament\Resources\Constructors\ConstructorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConstructors extends ListRecords
{
    protected static string $resource = ConstructorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
