<?php

namespace App\Filament\Resources\Constructors\RelationManagers;

use App\Filament\GlobalSeasonSelector;
use App\Filament\Resources\Constructors\ConstructorResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('season.name')->label('Season')->searchable(),
                TextColumn::make('driver.name')->label('Driver')->searchable(),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_to')->date(),
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
