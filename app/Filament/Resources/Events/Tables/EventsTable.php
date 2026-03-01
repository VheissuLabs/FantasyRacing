<?php

namespace App\Filament\Resources\Events\Tables;

use App\Filament\GlobalSeasonSelector;
use App\Jobs\CalculateEventPoints;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $seasonId = GlobalSeasonSelector::getCurrentSeasonId();

                if ($seasonId) {
                    $query->where('season_id', $seasonId);
                }
            })
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('track.name')->label('Track'),
                TextColumn::make('type'),
                TextColumn::make('scheduled_at')->dateTime(),
                TextColumn::make('status'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'race' => 'Race',
                        'qualifying' => 'Qualifying',
                        'sprint' => 'Sprint',
                        'sprint_qualifying' => 'Sprint Qualifying',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'locked' => 'Locked',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('calculatePoints')
                    ->label('Calculate Points')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->modalDescription('This will calculate fantasy points for all teams in this event and refresh season stats.')
                    ->visible(fn (Event $record): bool => $record->status === 'completed' && $record->results()->exists())
                    ->action(function (Event $record) {
                        CalculateEventPoints::dispatch($record);

                        Notification::make()
                            ->title('Points calculation queued')
                            ->body("Points calculation for {$record->name} has been dispatched.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('calculateAllPoints')
                        ->label('Calculate Points')
                        ->icon('heroicon-o-calculator')
                        ->requiresConfirmation()
                        ->modalDescription('Calculate fantasy points for all selected completed events.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $dispatched = 0;

                            foreach ($records as $event) {
                                if ($event->status === 'completed' && $event->results()->exists()) {
                                    CalculateEventPoints::dispatch($event);
                                    $dispatched++;
                                }
                            }

                            Notification::make()
                                ->title("{$dispatched} points calculation(s) queued")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
