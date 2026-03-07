<?php

namespace App\Filament\Resources\Franchises\RelationManagers;

use App\Filament\Resources\Franchises\FranchiseResource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ManagersRelationManager extends RelationManager
{
    protected static string $resource = FranchiseResource::class;

    protected static string $relationship = 'managers';

    public function table(Table $table): Table
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user?->isSuperAdmin() ?? false;

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('pivot.created_at')
                    ->label('Assigned At')
                    ->dateTime(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->visible($isSuperAdmin),
            ])
            ->recordActions([
                DetachAction::make()
                    ->visible($isSuperAdmin),
            ])
            ->toolbarActions([
                DetachBulkAction::make()
                    ->visible($isSuperAdmin),
            ]);
    }
}
