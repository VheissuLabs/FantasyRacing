<?php

namespace App\Filament\Resources\Leagues\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvitesRelationManager extends RelationManager
{
    protected static string $relationship = 'invites';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('inviter.name')->label('Invited By'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'accepted' => 'success',
                        'pending' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('expires_at')->label('Expires')->dateTime(),
                TextColumn::make('accepted_at')->label('Accepted')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'expired' => 'Expired',
                    ]),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
