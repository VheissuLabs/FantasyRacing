<?php

namespace App\Filament\Resources\Leagues;

use App\Filament\Resources\Leagues\Pages\CreateLeague;
use App\Filament\Resources\Leagues\Pages\EditLeague;
use App\Filament\Resources\Leagues\Pages\ListLeagues;
use App\Filament\Resources\Leagues\Pages\ViewLeague;
use App\Filament\Resources\Leagues\RelationManagers\FantasyTeamsRelationManager;
use App\Filament\Resources\Leagues\RelationManagers\InvitesRelationManager;
use App\Filament\Resources\Leagues\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Leagues\Schemas\LeagueForm;
use App\Filament\Resources\Leagues\Schemas\LeagueInfolist;
use App\Filament\Resources\Leagues\Tables\LeaguesTable;
use App\Models\League;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LeagueResource extends Resource
{
    protected static ?string $model = League::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static UnitEnum|string|null $navigationGroup = 'Fantasy';

    public static function form(Schema $schema): Schema
    {
        return LeagueForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeagueInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaguesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
            FantasyTeamsRelationManager::class,
            InvitesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeagues::route('/'),
            'create' => CreateLeague::route('/create'),
            'view' => ViewLeague::route('/{record}'),
            'edit' => EditLeague::route('/{record}/edit'),
        ];
    }
}
