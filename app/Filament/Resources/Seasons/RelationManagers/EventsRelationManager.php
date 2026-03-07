<?php

namespace App\Filament\Resources\Seasons\RelationManagers;

use App\Filament\Resources\Seasons\SeasonResource;
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
    protected static string $resource = SeasonResource::class;

    protected static string $relationship = 'events';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('track.name')->label('Track'),
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
                SelectFilter::make('status')->options([
                    'scheduled' => 'Scheduled',
                    'locked' => 'Locked',
                    'completed' => 'Completed',
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
