<?php

namespace App\Filament\Resources\FantasyTeams\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RosterRelationManager extends RelationManager
{
    protected static string $relationship = 'roster';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('entity_id')
            ->columns([
                TextColumn::make('entity_type')->label('Type')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'driver' => 'success',
                        'constructor' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('entity.name')->label('Name'),
                IconColumn::make('in_seat')->label('In Seat')->boolean(),
                TextColumn::make('acquired_at')->label('Acquired')->date(),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
