<?php

namespace App\Filament\Resources\Constructors\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ConstructorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('franchise.name')
                    ->label('Franchise'),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('logo_path'),
                IconEntry::make('is_active')
                    ->boolean(),
            ]);
    }
}
