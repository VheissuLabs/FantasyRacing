<?php

namespace App\Filament\Resources\DraftSessions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DraftOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Draft Order';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pick_number')
            ->defaultSort('pick_number')
            ->columns([
                TextColumn::make('pick_number')->label('#')->sortable(),
                TextColumn::make('round')->sortable(),
                TextColumn::make('round_pick')->label('Round Pick'),
                TextColumn::make('fantasyTeam.name')->label('Team'),
                TextColumn::make('entity_type_restriction')->label('Restriction')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'driver' => 'success',
                        'constructor' => 'primary',
                        default => 'gray',
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
