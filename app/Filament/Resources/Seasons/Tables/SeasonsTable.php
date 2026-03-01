<?php

namespace App\Filament\Resources\Seasons\Tables;

use App\Filament\GlobalFranchiseSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SeasonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $franchiseId = GlobalFranchiseSelector::getCurrentFranchiseId();

                if ($franchiseId) {
                    $query->where('franchise_id', $franchiseId);
                }
            })
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('year'),
                TextColumn::make('franchise.name')->label('Franchise'),
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
