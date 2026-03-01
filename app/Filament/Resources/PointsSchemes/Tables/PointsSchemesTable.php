<?php

namespace App\Filament\Resources\PointsSchemes\Tables;

use App\Filament\GlobalFranchiseSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PointsSchemesTable
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
                TextColumn::make('franchise.name')->label('Franchise')->sortable(),
                TextColumn::make('event_type')->label('Event Type')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'race' => 'danger',
                        'qualifying' => 'warning',
                        'sprint' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('position')->sortable(),
                TextColumn::make('points')->sortable(),
            ])
            ->defaultSort('event_type')
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'race' => 'Race',
                        'qualifying' => 'Qualifying',
                        'sprint' => 'Sprint',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
