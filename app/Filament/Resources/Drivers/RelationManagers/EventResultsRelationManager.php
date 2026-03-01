<?php

namespace App\Filament\Resources\Drivers\RelationManagers;

use App\Filament\GlobalSeasonSelector;
use App\Filament\Resources\Drivers\DriverResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventResultsRelationManager extends RelationManager
{
    protected static string $resource = DriverResource::class;

    protected static string $relationship = 'eventResults';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $seasonId = GlobalSeasonSelector::getCurrentSeasonId();

                if ($seasonId) {
                    $query->whereHas('event', fn ($query) => $query->where('season_id', $seasonId));
                }
            })
            ->recordTitleAttribute('event.name')
            ->columns([
                Tables\Columns\TextColumn::make('event.name')->label('Event')->searchable(),
                Tables\Columns\TextColumn::make('finish_position')->label('Pos'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\BooleanColumn::make('fastest_lap')->label('FL'),
                Tables\Columns\BooleanColumn::make('driver_of_the_day')->label('DOTD'),
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
