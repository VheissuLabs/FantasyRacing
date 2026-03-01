<?php

namespace App\Filament\Resources\Franchises;

use App\Filament\Resources\Franchises\Pages\CreateFranchise;
use App\Filament\Resources\Franchises\Pages\EditFranchise;
use App\Filament\Resources\Franchises\Pages\ListFranchises;
use App\Filament\Resources\Franchises\Pages\ViewFranchise;
use App\Filament\Resources\Franchises\Schemas\FranchiseForm;
use App\Filament\Resources\Franchises\Schemas\FranchiseInfolist;
use App\Filament\Resources\Franchises\Tables\FranchisesTable;
use App\Models\Franchise;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FranchiseResource extends Resource
{
    protected static ?string $model = Franchise::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function form(Schema $schema): Schema
    {
        return FranchiseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FranchiseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FranchisesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Franchises\RelationManagers\SeasonsRelationManager::class,
            \App\Filament\Resources\Franchises\RelationManagers\ManagersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFranchises::route('/'),
            'create' => CreateFranchise::route('/create'),
            'view' => ViewFranchise::route('/{record}'),
            'edit' => EditFranchise::route('/{record}/edit'),
        ];
    }
}
