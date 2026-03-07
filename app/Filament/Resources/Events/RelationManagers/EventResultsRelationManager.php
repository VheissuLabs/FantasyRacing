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

class EventResultsRelationManager extends RelationManager
{
    protected static string $resource = EventResource::class;

    protected static string $relationship = 'results';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('driver.name')
            ->columns([
                TextColumn::make('finish_position')->label('Pos'),
                TextColumn::make('driver.name')->label('Driver')->searchable(),
                TextColumn::make('constructor.name')->label('Constructor'),
                TextColumn::make('status'),
                IconColumn::make('fastest_lap')->boolean()->label('FL'),
                IconColumn::make('driver_of_the_day')->boolean()->label('DOTD'),
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
