<?php

namespace App\Filament\Resources\FantasyTeams\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FantasyTeamInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('user.name')->label('Owner'),
            TextEntry::make('league.name')->label('League'),
            TextEntry::make('league.season.name')->label('Season'),
            TextEntry::make('created_at')->label('Created')->dateTime(),
        ]);
    }
}
