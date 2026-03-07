<?php

namespace App\Filament\Resources\Leagues\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.name')->label('User')->searchable(),
                TextColumn::make('user.email')->label('Email'),
                TextColumn::make('role')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'commissioner' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('joined_at')->label('Joined')->date(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
