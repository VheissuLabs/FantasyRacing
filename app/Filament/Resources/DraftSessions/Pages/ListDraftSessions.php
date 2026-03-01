<?php

namespace App\Filament\Resources\DraftSessions\Pages;

use App\Filament\Resources\DraftSessions\DraftSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDraftSessions extends ListRecords
{
    protected static string $resource = DraftSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
