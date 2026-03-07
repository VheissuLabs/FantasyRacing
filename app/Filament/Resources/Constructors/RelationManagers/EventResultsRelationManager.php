<?php

namespace App\Filament\Resources\Constructors\RelationManagers;

use App\Filament\GlobalSeasonSelector;
use App\Filament\Resources\Constructors\ConstructorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventResultsRelationManager extends RelationManager
{
    protected static string $resource = ConstructorResource::class;

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
            ->recordTitleAttribute('driver.name')
            ->columns([
                TextColumn::make('event.name')->label('Event')->searchable(),
                TextColumn::make('driver.name')->label('Driver')->searchable(),
                TextColumn::make('finish_position')->label('Pos'),
                TextColumn::make('status'),
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
