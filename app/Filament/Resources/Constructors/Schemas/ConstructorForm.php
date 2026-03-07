<?php

namespace App\Filament\Resources\Constructors\Schemas;

use App\Models\Country;
use App\Models\Franchise;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConstructorForm
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
                Select::make('country_id')
                    ->label('Country')
                    ->options(Country::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                TextInput::make('slug')->required(),
                TextInput::make('logo_path'),
                Toggle::make('is_active'),
            ]);
    }
}
