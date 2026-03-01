<?php

namespace App\Filament\Resources\FantasyTeams\Tables;

use App\Filament\GlobalSeasonSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FantasyTeamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $seasonId = GlobalSeasonSelector::getCurrentSeasonId();

                if ($seasonId) {
                    $query->whereHas('league', fn ($query) => $query->where('season_id', $seasonId));
                }
            })
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Owner')->searchable(),
                TextColumn::make('league.name')->label('League')->sortable(),
                TextColumn::make('league.season.name')->label('Season'),
                TextColumn::make('roster_count')
                    ->label('Roster Size')
                    ->counts('roster')
                    ->sortable(),
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
