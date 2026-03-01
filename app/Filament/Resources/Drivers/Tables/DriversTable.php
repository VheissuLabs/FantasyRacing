<?php

namespace App\Filament\Resources\Drivers\Tables;

use App\Filament\GlobalSeasonSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $seasonId = GlobalSeasonSelector::getCurrentSeasonId();

                if ($seasonId) {
                    $query
                        ->whereHas('seasonDrivers', fn ($query) => $query->where('season_id', $seasonId))
                        ->with(['seasonDrivers' => fn ($query) => $query->where('season_id', $seasonId)->with('constructor')]);
                }
            })
            ->columns([
                TextColumn::make('seasonDrivers.number')
                    ->label('#')
                    ->getStateUsing(fn ($record) => $record->seasonDrivers->first()?->number),
                TextColumn::make('name')->searchable(),
                TextColumn::make('seasonDrivers.constructor.name')
                    ->label('Constructor')
                    ->getStateUsing(fn ($record) => $record->seasonDrivers->first()?->constructor?->name),
                TextColumn::make('country.name')->label('Nationality'),
                ToggleColumn::make('is_active'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
