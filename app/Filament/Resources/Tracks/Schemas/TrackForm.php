<?php

namespace App\Filament\Resources\Tracks\Schemas;

use App\Models\Country;
use App\Models\Franchise;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TrackForm
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
                TextInput::make('location')->required(),
                Select::make('country_id')
                    ->label('Country')
                    ->options(Country::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('photo_path'),
            ]);
    }
}
