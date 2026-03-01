<?php

namespace App\Filament\Resources\Franchises\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FranchiseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('description'),
                TextEntry::make('logo_path'),
                IconEntry::make('is_active')
                    ->boolean(),
            ]);
    }
}
