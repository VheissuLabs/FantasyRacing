<?php

namespace App\Filament\Resources\DraftSessions\Pages;

use App\Filament\Resources\DraftSessions\DraftSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDraftSession extends EditRecord
{
    protected static string $resource = DraftSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
