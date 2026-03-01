<?php

namespace App\Filament\Resources\DraftSessions\Pages;

use App\Filament\Resources\DraftSessions\DraftSessionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDraftSession extends ViewRecord
{
    protected static string $resource = DraftSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
