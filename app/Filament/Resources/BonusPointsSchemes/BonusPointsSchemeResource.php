<?php

namespace App\Filament\Resources\BonusPointsSchemes;

use App\Filament\Resources\BonusPointsSchemes\Pages\CreateBonusPointsScheme;
use App\Filament\Resources\BonusPointsSchemes\Pages\EditBonusPointsScheme;
use App\Filament\Resources\BonusPointsSchemes\Pages\ListBonusPointsSchemes;
use App\Filament\Resources\BonusPointsSchemes\Schemas\BonusPointsSchemeForm;
use App\Filament\Resources\BonusPointsSchemes\Tables\BonusPointsSchemesTable;
use App\Models\BonusPointsScheme;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BonusPointsSchemeResource extends Resource
{
    protected static ?string $model = BonusPointsScheme::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static \UnitEnum|string|null $navigationGroup = 'Scoring';

    protected static ?string $navigationLabel = 'Bonus Points';

    public static function form(Schema $schema): Schema
    {
        return BonusPointsSchemeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BonusPointsSchemesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBonusPointsSchemes::route('/'),
            'create' => CreateBonusPointsScheme::route('/create'),
            'edit' => EditBonusPointsScheme::route('/{record}/edit'),
        ];
    }
}
