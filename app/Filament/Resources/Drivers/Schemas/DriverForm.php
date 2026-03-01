<?php

namespace App\Filament\Resources\Drivers\Schemas;

use App\Models\Country;
use App\Models\Franchise;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DriverForm
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
                TextInput::make('slug')->required(),
                TextInput::make('number')->numeric(),
                Select::make('country_id')
                    ->label('Nationality')
                    ->options(Country::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                TextInput::make('photo_path'),
                TextInput::make('openf1_driver_number')->numeric(),
                Toggle::make('is_active'),
            ]);
    }
}
