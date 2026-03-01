<?php

namespace App\Filament\Resources\Constructors;

use App\Filament\Resources\Constructors\Pages\CreateConstructor;
use App\Filament\Resources\Constructors\Pages\EditConstructor;
use App\Filament\Resources\Constructors\Pages\ListConstructors;
use App\Filament\Resources\Constructors\Pages\ViewConstructor;
use App\Filament\Resources\Constructors\Schemas\ConstructorForm;
use App\Filament\Resources\Constructors\Schemas\ConstructorInfolist;
use App\Filament\Resources\Constructors\Tables\ConstructorsTable;
use App\Models\Constructor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConstructorResource extends Resource
{
    protected static ?string $model = Constructor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function form(Schema $schema): Schema
    {
        return ConstructorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConstructorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConstructorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Constructors\RelationManagers\SeasonDriversRelationManager::class,
            \App\Filament\Resources\Constructors\RelationManagers\EventResultsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConstructors::route('/'),
            'create' => CreateConstructor::route('/create'),
            'view' => ViewConstructor::route('/{record}'),
            'edit' => EditConstructor::route('/{record}/edit'),
        ];
    }
}
