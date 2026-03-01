<?php

namespace App\Filament\Resources\DraftSessions;

use App\Filament\Resources\DraftSessions\Pages\CreateDraftSession;
use App\Filament\Resources\DraftSessions\Pages\EditDraftSession;
use App\Filament\Resources\DraftSessions\Pages\ListDraftSessions;
use App\Filament\Resources\DraftSessions\Pages\ViewDraftSession;
use App\Filament\Resources\DraftSessions\RelationManagers\DraftOrdersRelationManager;
use App\Filament\Resources\DraftSessions\RelationManagers\DraftPicksRelationManager;
use App\Filament\Resources\DraftSessions\Schemas\DraftSessionForm;
use App\Filament\Resources\DraftSessions\Schemas\DraftSessionInfolist;
use App\Filament\Resources\DraftSessions\Tables\DraftSessionsTable;
use App\Models\DraftSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DraftSessionResource extends Resource
{
    protected static ?string $model = DraftSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static \UnitEnum|string|null $navigationGroup = 'Fantasy';

    public static function form(Schema $schema): Schema
    {
        return DraftSessionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DraftSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DraftSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DraftOrdersRelationManager::class,
            DraftPicksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDraftSessions::route('/'),
            'create' => CreateDraftSession::route('/create'),
            'view' => ViewDraftSession::route('/{record}'),
            'edit' => EditDraftSession::route('/{record}/edit'),
        ];
    }
}
