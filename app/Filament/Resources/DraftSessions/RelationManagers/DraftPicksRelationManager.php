<?php

namespace App\Filament\Resources\DraftSessions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DraftPicksRelationManager extends RelationManager
{
    protected static string $relationship = 'picks';

    protected static ?string $title = 'Draft Picks';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pick_number')
            ->defaultSort('pick_number')
            ->columns([
                TextColumn::make('pick_number')->label('#')->sortable(),
                TextColumn::make('fantasyTeam.name')->label('Team'),
                TextColumn::make('entity_type')->label('Type')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'driver' => 'success',
                        'constructor' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('entity.name')->label('Pick'),
                IconColumn::make('is_auto_pick')->label('Auto Pick')->boolean(),
                TextColumn::make('picked_at')->label('Picked At')->dateTime(),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
