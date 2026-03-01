<?php

namespace App\Filament\Resources\Leagues\Tables;

use App\Filament\GlobalSeasonSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeaguesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $seasonId = GlobalSeasonSelector::getCurrentSeasonId();

                if ($seasonId) {
                    $query->where('season_id', $seasonId);
                }
            })
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('season.name')->label('Season')->sortable(),
                TextColumn::make('franchise.name')->label('Franchise'),
                TextColumn::make('commissioner.name')->label('Commissioner'),
                TextColumn::make('visibility')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'public' => 'success',
                        'private' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('join_policy')->label('Join Policy')->badge(),
                TextColumn::make('members_count')
                    ->label('Members')
                    ->counts('members')
                    ->sortable(),
                ToggleColumn::make('is_active'),
            ])
            ->filters([
                SelectFilter::make('visibility')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                    ]),
                SelectFilter::make('join_policy')
                    ->label('Join Policy')
                    ->options([
                        'open' => 'Open',
                        'request' => 'Request',
                        'invite' => 'Invite Only',
                    ]),
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
