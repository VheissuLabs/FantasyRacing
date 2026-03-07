<?php

namespace App\Filament\Resources\Tracks\RelationManagers;

use App\Filament\GlobalSeasonSelector;
use App\Filament\Resources\Tracks\TrackResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $resource = TrackResource::class;

    protected static string $relationship = 'events';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $seasonId = GlobalSeasonSelector::getCurrentSeasonId();

                if ($seasonId) {
                    $query->where('season_id', $seasonId);
                }
            })
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type'),
                TextColumn::make('scheduled_at')->dateTime(),
                TextColumn::make('status'),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'race' => 'Race',
                    'qualifying' => 'Qualifying',
                    'sprint' => 'Sprint',
                    'sprint_qualifying' => 'Sprint Qualifying',
                ]),
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
