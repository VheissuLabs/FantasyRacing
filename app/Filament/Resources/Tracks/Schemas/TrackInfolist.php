<?php

namespace App\Filament\Resources\Tracks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TrackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('franchise.name')
                    ->label('Franchise'),
                TextEntry::make('name'),
                TextEntry::make('location'),
                TextEntry::make('country.name')
                    ->label('Country'),
                TextEntry::make('photo_path'),
            ]);
    }
}
