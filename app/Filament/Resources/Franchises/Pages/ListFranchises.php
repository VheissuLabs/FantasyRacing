<?php

namespace App\Filament\Resources\Franchises\Pages;

use App\Filament\Resources\Franchises\FranchiseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFranchises extends ListRecords
{
    protected static string $resource = FranchiseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
