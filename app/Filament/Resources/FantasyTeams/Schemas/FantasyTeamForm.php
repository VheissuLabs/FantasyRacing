<?php

namespace App\Filament\Resources\FantasyTeams\Schemas;

use App\Models\League;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FantasyTeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('league_id')
                ->label('League')
                ->options(League::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),

            Select::make('user_id')
                ->label('Owner')
                ->options(User::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),

            TextInput::make('name')->required()->maxLength(255),
        ]);
    }
}
