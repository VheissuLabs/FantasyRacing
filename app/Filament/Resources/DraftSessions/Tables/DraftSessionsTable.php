<?php

namespace App\Filament\Resources\DraftSessions\Tables;

use App\Filament\GlobalSeasonSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DraftSessionsTable
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
                TextColumn::make('league.name')->label('League')->sortable()->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'gray',
                        'active' => 'success',
                        'paused' => 'warning',
                        'completed' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('scheduled_at')->label('Scheduled')->dateTime()->sortable(),
                TextColumn::make('started_at')->label('Started')->dateTime(),
                TextColumn::make('current_pick_number')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state, $record) => "{$state} / {$record->total_picks}"),
                TextColumn::make('pick_time_limit_seconds')->label('Time Limit (s)'),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'completed' => 'Completed',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'snake' => 'Snake',
                        'auction' => 'Auction',
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
