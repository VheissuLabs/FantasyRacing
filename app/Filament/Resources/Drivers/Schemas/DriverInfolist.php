<?php

namespace App\Filament\Resources\Drivers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DriverInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('franchise.name')
                    ->label('Franchise'),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('number')
                    ->label('#'),
                TextEntry::make('country.name')
                    ->label('Nationality'),
                TextEntry::make('photo_path'),
                IconEntry::make('is_active')
                    ->boolean(),
            ]);
    }
}
