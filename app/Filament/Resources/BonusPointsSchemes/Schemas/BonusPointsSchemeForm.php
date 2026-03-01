<?php

namespace App\Filament\Resources\BonusPointsSchemes\Schemas;

use App\Models\Franchise;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BonusPointsSchemeForm
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

            TextInput::make('bonus_key')
                ->label('Bonus Key')
                ->required()
                ->maxLength(64),

            Select::make('applies_to')
                ->label('Applies To')
                ->options([
                    'driver' => 'Driver',
                    'constructor' => 'Constructor',
                ])
                ->required(),

            TextInput::make('points')
                ->numeric()
                ->required(),
        ]);
    }
}
