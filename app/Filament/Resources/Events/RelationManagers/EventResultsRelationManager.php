<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\Events\EventResource;
use App\Jobs\CalculateEventPoints;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventResultsRelationManager extends RelationManager
{
    protected static string $resource = EventResource::class;

    protected static string $relationship = 'results';

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Select::make('driver_id')
                ->relationship('driver', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('constructor_id')
                ->relationship('constructor', 'name')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('finish_position')
                ->label('Finish Position')
                ->numeric()
                ->minValue(1)
                ->maxValue(22),
            TextInput::make('grid_position')
                ->label('Grid Position')
                ->numeric()
                ->minValue(1)
                ->maxValue(22),
            Select::make('status')
                ->options([
                    'classified' => 'Classified',
                    'dnf' => 'DNF',
                    'dns' => 'DNS',
                    'dsq' => 'DSQ',
                    'not_classified' => 'Not Classified',
                ])
                ->required(),
            Checkbox::make('fastest_lap')->label('Fastest Lap'),
            Checkbox::make('driver_of_the_day')->label('Driver of the Day'),
            TextInput::make('overtakes_made')
                ->label('Overtakes')
                ->numeric()
                ->minValue(0),
            TextInput::make('q1_time')->label('Q1 Time'),
            TextInput::make('q2_time')->label('Q2 Time'),
            TextInput::make('q3_time')->label('Q3 Time'),
            Checkbox::make('teammate_outqualified')->label('Outqualified Teammate'),
            Checkbox::make('points_eligible')->label('Points Eligible')->default(true),
            Select::make('data_source')
                ->options([
                    'manual' => 'Manual',
                    'jolpica' => 'Jolpica',
                    'openf1' => 'OpenF1',
                    'derived' => 'Derived',
                ])
                ->default('manual'),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('driver.name')
            ->defaultSort('finish_position')
            ->columns([
                TextColumn::make('finish_position')->label('Pos')->sortable(),
                TextColumn::make('driver.name')->label('Driver')->searchable()->sortable(),
                TextColumn::make('constructor.name')->label('Constructor')->sortable(),
                TextColumn::make('grid_position')->label('Grid')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'classified' => 'success',
                        'dnf' => 'danger',
                        'dns' => 'warning',
                        'dsq' => 'danger',
                        'not_classified' => 'gray',
                    }),
                IconColumn::make('fastest_lap')->boolean()->label('FL'),
                IconColumn::make('driver_of_the_day')->boolean()->label('DOTD'),
                TextColumn::make('overtakes_made')->label('OT')->sortable(),
                TextColumn::make('fantasy_points')->label('Fantasy Pts')->sortable()
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('fia_points')->label('FIA Pts')->sortable()
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('data_source')
                    ->badge()
                    ->label('Source')
                    ->color(fn (string $state): string => match ($state) {
                        'jolpica' => 'success',
                        'openf1' => 'info',
                        'manual' => 'warning',
                        'derived' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('recalculatePoints')
                    ->label('Recalculate Points')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->action(function () {
                        $event = $this->getOwnerRecord();
                        CalculateEventPoints::dispatchSync($event);

                        Notification::make()
                            ->title('Points recalculated')
                            ->body("Points for {$event->name} have been recalculated.")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
