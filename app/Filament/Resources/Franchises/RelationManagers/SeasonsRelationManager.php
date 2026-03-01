<?php

namespace App\Filament\Resources\Franchises\RelationManagers;

use App\Filament\Resources\Franchises\FranchiseResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SeasonsRelationManager extends RelationManager
{
    protected static string $resource = FranchiseResource::class;

    protected static string $relationship = 'seasons';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('year'),
                Tables\Columns\ToggleColumn::make('is_active'),
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
