<?php

namespace App\Filament\Resources\FantasyTeams;

use App\Filament\Resources\FantasyTeams\Pages\CreateFantasyTeam;
use App\Filament\Resources\FantasyTeams\Pages\EditFantasyTeam;
use App\Filament\Resources\FantasyTeams\Pages\ListFantasyTeams;
use App\Filament\Resources\FantasyTeams\Pages\ViewFantasyTeam;
use App\Filament\Resources\FantasyTeams\RelationManagers\RosterRelationManager;
use App\Filament\Resources\FantasyTeams\Schemas\FantasyTeamForm;
use App\Filament\Resources\FantasyTeams\Schemas\FantasyTeamInfolist;
use App\Filament\Resources\FantasyTeams\Tables\FantasyTeamsTable;
use App\Models\FantasyTeam;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FantasyTeamResource extends Resource
{
    protected static ?string $model = FantasyTeam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static \UnitEnum|string|null $navigationGroup = 'Fantasy';

    public static function form(Schema $schema): Schema
    {
        return FantasyTeamForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FantasyTeamInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FantasyTeamsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RosterRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFantasyTeams::route('/'),
            'create' => CreateFantasyTeam::route('/create'),
            'view' => ViewFantasyTeam::route('/{record}'),
            'edit' => EditFantasyTeam::route('/{record}/edit'),
        ];
    }
}
