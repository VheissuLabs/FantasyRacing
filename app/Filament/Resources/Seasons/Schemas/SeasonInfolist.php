<?php

namespace App\Filament\Resources\Seasons\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SeasonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('franchise.name')
                    ->label('Franchise'),
                TextEntry::make('name'),
                TextEntry::make('year'),
                IconEntry::make('is_active')
                    ->boolean(),
            ]);
    }
}
