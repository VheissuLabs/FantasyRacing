<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Jobs\CalculateEventPoints;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calculatePoints')
                ->label('Calculate Points')
                ->icon('heroicon-o-calculator')
                ->requiresConfirmation()
                ->modalDescription('This will calculate fantasy points for all teams in this event and refresh season stats.')
                ->visible(fn (Event $record): bool => $record->status === 'completed' && $record->results()->exists())
                ->action(function (Event $record) {
                    CalculateEventPoints::dispatchSync($record);

                    Notification::make()
                        ->title('Points recalculated')
                        ->body("Points for {$record->name} have been recalculated.")
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
