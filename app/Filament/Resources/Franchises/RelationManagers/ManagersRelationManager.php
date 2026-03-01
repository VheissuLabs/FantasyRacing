<?php

namespace App\Filament\Resources\Franchises\RelationManagers;

use App\Filament\Resources\Franchises\FranchiseResource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ManagersRelationManager extends RelationManager
{
    protected static string $resource = FranchiseResource::class;

    protected static string $relationship = 'managers';

    public function table(Table $table): Table
    {
        $isSuperAdmin = Auth::user()?->isSuperAdmin() ?? false;

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Assigned At')
                    ->dateTime(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->visible($isSuperAdmin),
            ])
            ->actions([
                DetachAction::make()
                    ->visible($isSuperAdmin),
            ])
            ->bulkActions([
                DetachBulkAction::make()
                    ->visible($isSuperAdmin),
            ]);
    }
}
