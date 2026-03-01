<?php

namespace App\Filament\Resources\Seasons\RelationManagers;

use App\Filament\Resources\Seasons\SeasonResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SeasonConstructorsRelationManager extends RelationManager
{
    protected static string $resource = SeasonResource::class;

    protected static string $relationship = 'seasonConstructors';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('constructor.name')
            ->columns([
                Tables\Columns\TextColumn::make('constructor.name')->label('Constructor')->searchable(),
                Tables\Columns\TextColumn::make('constructor.slug'),
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
