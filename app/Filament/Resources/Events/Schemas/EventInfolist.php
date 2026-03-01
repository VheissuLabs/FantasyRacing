<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('season.name')
                    ->label('Season'),
                TextEntry::make('track.name')
                    ->label('Track'),
                TextEntry::make('name'),
                TextEntry::make('type'),
                TextEntry::make('scheduled_at')
                    ->dateTime(),
                TextEntry::make('locked_at')
                    ->dateTime(),
                TextEntry::make('status'),
                IconEntry::make('is_active')
                    ->boolean(),
            ]);
    }
}
