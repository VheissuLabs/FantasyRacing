<?php

namespace App\Filament\Resources\Constructors\Tables;

use App\Filament\GlobalSeasonSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ConstructorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $seasonId = GlobalSeasonSelector::getCurrentSeasonId();

                if ($seasonId) {
                    $query->whereHas('seasonConstructors', fn ($query) => $query->where('season_id', $seasonId));
                }
            })
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug'),
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
