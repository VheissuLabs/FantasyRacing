<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\Events\EventResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventResultsRelationManager extends RelationManager
{
    protected static string $resource = EventResource::class;

    protected static string $relationship = 'results';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('driver.name')
            ->columns([
                Tables\Columns\TextColumn::make('finish_position')->label('Pos'),
                Tables\Columns\TextColumn::make('driver.name')->label('Driver')->searchable(),
                Tables\Columns\TextColumn::make('constructor.name')->label('Constructor'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\BooleanColumn::make('fastest_lap')->label('FL'),
                Tables\Columns\BooleanColumn::make('driver_of_the_day')->label('DOTD'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
