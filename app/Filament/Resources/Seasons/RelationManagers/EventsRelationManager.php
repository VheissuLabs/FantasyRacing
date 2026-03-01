<?php

namespace App\Filament\Resources\Seasons\RelationManagers;

use App\Filament\Resources\Seasons\SeasonResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
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
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('track.name')->label('Track'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime(),
                Tables\Columns\TextColumn::make('status'),
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
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
