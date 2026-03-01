<?php

namespace App\Filament\Resources\Leagues\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LeagueInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('slug'),
            TextEntry::make('franchise.name')->label('Franchise'),
            TextEntry::make('season.name')->label('Season'),
            TextEntry::make('commissioner.name')->label('Commissioner'),
            TextEntry::make('visibility')->badge()
                ->color(fn (string $state) => match ($state) {
                    'public' => 'success',
                    'private' => 'danger',
                    default => 'gray',
                }),
            TextEntry::make('join_policy')->label('Join Policy')->badge(),
            TextEntry::make('max_teams')->label('Max Teams'),
            TextEntry::make('invite_code')->label('Invite Code'),
            TextEntry::make('description')->columnSpanFull(),
            IconEntry::make('is_active')->label('Active')->boolean(),
            TextEntry::make('draft_completed_at')->label('Draft Completed')->dateTime(),
        ]);
    }
}
