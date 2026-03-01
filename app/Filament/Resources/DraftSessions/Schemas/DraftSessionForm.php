<?php

namespace App\Filament\Resources\DraftSessions\Schemas;

use App\Models\League;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DraftSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('league_id')
                ->label('League')
                ->options(League::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),

            Select::make('type')
                ->options([
                    'snake' => 'Snake',
                    'auction' => 'Auction',
                ])
                ->required(),

            Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'active' => 'Active',
                    'paused' => 'Paused',
                    'completed' => 'Completed',
                ])
                ->required()
                ->default('pending'),

            DateTimePicker::make('scheduled_at')->label('Scheduled At'),

            TextInput::make('pick_time_limit_seconds')
                ->label('Pick Time Limit (seconds)')
                ->numeric()
                ->minValue(10),

            TextInput::make('total_picks')
                ->label('Total Picks')
                ->numeric()
                ->minValue(1),
        ]);
    }
}
