<?php

namespace App\Filament\Resources\Drivers\RelationManagers;

use App\Filament\GlobalSeasonSelector;
use App\Filament\Resources\Drivers\DriverResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('event.name')->label('Event')->searchable(),
                TextColumn::make('finish_position')->label('Pos'),
                TextColumn::make('status'),
                IconColumn::make('fastest_lap')->boolean()->label('FL'),
                IconColumn::make('driver_of_the_day')->boolean()->label('DOTD'),
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
