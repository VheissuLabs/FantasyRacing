<?php

namespace App\Filament\Resources\BonusPointsSchemes\Tables;

use App\Filament\GlobalFranchiseSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BonusPointsSchemesTable
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
                TextColumn::make('bonus_key')->label('Bonus Key')->searchable(),
                TextColumn::make('applies_to')->label('Applies To')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'driver' => 'success',
                        'constructor' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('points')->sortable(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'race' => 'Race',
                        'qualifying' => 'Qualifying',
                        'sprint' => 'Sprint',
                    ]),
                SelectFilter::make('applies_to')
                    ->label('Applies To')
                    ->options([
                        'driver' => 'Driver',
                        'constructor' => 'Constructor',
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
