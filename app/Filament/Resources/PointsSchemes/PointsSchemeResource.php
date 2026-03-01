<?php

namespace App\Filament\Resources\PointsSchemes;

use App\Filament\Resources\PointsSchemes\Pages\CreatePointsScheme;
use App\Filament\Resources\PointsSchemes\Pages\EditPointsScheme;
use App\Filament\Resources\PointsSchemes\Pages\ListPointsSchemes;
use App\Filament\Resources\PointsSchemes\Schemas\PointsSchemeForm;
use App\Filament\Resources\PointsSchemes\Tables\PointsSchemesTable;
use App\Models\PointsScheme;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PointsSchemeResource extends Resource
{
    protected static ?string $model = PointsScheme::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static \UnitEnum|string|null $navigationGroup = 'Scoring';

    protected static ?string $navigationLabel = 'Position Points';

    public static function form(Schema $schema): Schema
    {
        return PointsSchemeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PointsSchemesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPointsSchemes::route('/'),
            'create' => CreatePointsScheme::route('/create'),
            'edit' => EditPointsScheme::route('/{record}/edit'),
        ];
    }
}
