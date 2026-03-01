<?php

namespace App\Filament\Resources\Seasons\Schemas;

use App\Models\Franchise;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SeasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('franchise_id')
                    ->label('Franchise')
                    ->options(Franchise::all()->pluck('name', 'id'))
                    ->required(),
                TextInput::make('name')->required(),
                TextInput::make('year')->numeric()->required(),
                Toggle::make('is_active'),
            ]);
    }
}
