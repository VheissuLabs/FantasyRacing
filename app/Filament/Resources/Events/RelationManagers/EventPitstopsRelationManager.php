<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\Events\EventResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventPitstopsRelationManager extends RelationManager
{
    protected static string $resource = EventResource::class;

    protected static string $relationship = 'pitstops';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('driver.name')
            ->defaultSort('stop_time_seconds')
            ->columns([
                TextColumn::make('constructor.name')
                    ->label('Constructor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stop_lap')
                    ->label('Lap')
                    ->sortable(),
                TextColumn::make('stop_time_seconds')
                    ->label('Stop Time (s)')
                    ->sortable()
                    ->numeric(decimalPlaces: 3),
                IconColumn::make('is_fastest_of_event')
                    ->boolean()
                    ->label('Fastest'),
                TextColumn::make('data_source')
                    ->badge()
                    ->label('Source')
                    ->color(fn (string $state): string => match ($state) {
                        'jolpica' => 'success',
                        'manual' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
