<?php

namespace App\Filament\Resources\Tracks\Tables;

use App\Filament\GlobalFranchiseSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TracksTable
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
                TextColumn::make('name')->searchable(),
                TextColumn::make('location'),
                TextColumn::make('country')->label('Country'),
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
