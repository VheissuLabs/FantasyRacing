<?php

namespace App\Filament\Resources\Constructors\RelationManagers;

use App\Filament\GlobalSeasonSelector;
use App\Filament\Resources\Constructors\ConstructorResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SeasonDriversRelationManager extends RelationManager
{
    protected static string $resource = ConstructorResource::class;

    protected static string $relationship = 'seasonDrivers';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $seasonId = GlobalSeasonSelector::getCurrentSeasonId();

                if ($seasonId) {
                    $query->where('season_id', $seasonId);
                }
            })
            ->recordTitleAttribute('driver.name')
            ->columns([
                Tables\Columns\TextColumn::make('season.name')->label('Season')->searchable(),
                Tables\Columns\TextColumn::make('driver.name')->label('Driver')->searchable(),
                Tables\Columns\TextColumn::make('effective_from')->date(),
                Tables\Columns\TextColumn::make('effective_to')->date(),
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
