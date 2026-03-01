<?php

namespace App\Filament\Resources\PointsSchemes\Schemas;

use App\Models\Franchise;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PointsSchemeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('franchise_id')
                ->label('Franchise')
                ->options(Franchise::orderBy('name')->pluck('name', 'id'))
                ->required(),

            Select::make('event_type')
                ->label('Event Type')
                ->options([
                    'race' => 'Race',
                    'qualifying' => 'Qualifying',
                    'sprint' => 'Sprint',
                ])
                ->required(),

            TextInput::make('position')
                ->numeric()
                ->minValue(1)
                ->required(),

            TextInput::make('points')
                ->numeric()
                ->required(),
        ]);
    }
}
