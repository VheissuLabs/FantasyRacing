<?php

namespace App\Filament\Resources\Franchises\Pages;

use App\Filament\Resources\Franchises\FranchiseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFranchise extends EditRecord
{
    protected static string $resource = FranchiseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
