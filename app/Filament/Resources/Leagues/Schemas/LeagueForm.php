<?php

namespace App\Filament\Resources\Leagues\Schemas;

use App\Models\Franchise;
use App\Models\Season;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LeagueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('franchise_id')
                ->label('Franchise')
                ->options(Franchise::orderBy('name')->pluck('name', 'id'))
                ->required()
                ->reactive(),

            Select::make('season_id')
                ->label('Season')
                ->options(fn ($get) => Season::when(
                    $get('franchise_id'),
                    fn ($query, $franchiseId) => $query->where('franchise_id', $franchiseId)
                )->orderByDesc('year')->pluck('name', 'id'))
                ->required(),

            Select::make('commissioner_id')
                ->label('Commissioner')
                ->options(User::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),

            TextInput::make('name')->required()->maxLength(255),

            TextInput::make('slug')->required()->maxLength(255),

            Textarea::make('description')->rows(3)->columnSpanFull(),

            TextInput::make('max_teams')
                ->label('Max Teams')
                ->numeric()
                ->minValue(2),

            TextInput::make('invite_code')
                ->label('Invite Code')
                ->maxLength(32),

            Select::make('visibility')
                ->options([
                    'public' => 'Public',
                    'private' => 'Private',
                ])
                ->required(),

            Select::make('join_policy')
                ->label('Join Policy')
                ->options([
                    'open' => 'Open',
                    'request' => 'Request',
                    'invite' => 'Invite Only',
                ])
                ->required(),

            Toggle::make('is_active')->default(true),
        ]);
    }
}
