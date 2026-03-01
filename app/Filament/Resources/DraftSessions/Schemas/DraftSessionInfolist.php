<?php

namespace App\Filament\Resources\DraftSessions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DraftSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('league.name')->label('League'),
            TextEntry::make('type')->badge(),
            TextEntry::make('status')->badge()
                ->color(fn (string $state) => match ($state) {
                    'pending' => 'gray',
                    'active' => 'success',
                    'paused' => 'warning',
                    'completed' => 'primary',
                    default => 'gray',
                }),
            TextEntry::make('scheduled_at')->label('Scheduled')->dateTime(),
            TextEntry::make('started_at')->label('Started')->dateTime(),
            TextEntry::make('completed_at')->label('Completed')->dateTime(),
            TextEntry::make('current_pick_number')
                ->label('Progress')
                ->formatStateUsing(fn ($state, $record) => "{$state} / {$record->total_picks}"),
            TextEntry::make('pick_time_limit_seconds')->label('Time Limit (s)'),
            TextEntry::make('pauser.name')->label('Paused By'),
        ]);
    }
}
