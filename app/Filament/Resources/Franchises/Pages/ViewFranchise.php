<?php

namespace App\Filament\Resources\Franchises\Pages;

use App\Filament\Resources\Franchises\FranchiseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFranchise extends ViewRecord
{
    protected static string $resource = FranchiseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
