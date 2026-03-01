<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Season;
use App\Models\Track;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->label('Season')
                    ->options(Season::all()->pluck('name', 'id'))
                    ->required(),
                Select::make('track_id')
                    ->label('Track')
                    ->options(Track::all()->pluck('name', 'id'))
                    ->required(),
                TextInput::make('name')->required(),
                Select::make('type')
                    ->options([
                        'race' => 'Race',
                        'qualifying' => 'Qualifying',
                        'sprint' => 'Sprint',
                        'sprint_qualifying' => 'Sprint Qualifying',
                        'practice' => 'Practice',
                    ])
                    ->required(),
                DateTimePicker::make('scheduled_at'),
                DateTimePicker::make('locked_at'),
                TextInput::make('openf1_session_key')->numeric(),
                Select::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'locked' => 'Locked',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Toggle::make('is_active'),
            ]);
    }
}
