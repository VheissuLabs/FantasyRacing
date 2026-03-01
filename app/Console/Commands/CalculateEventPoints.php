<?php

namespace App\Console\Commands;

use App\Jobs\CalculateEventPoints as CalculateEventPointsJob;
use App\Models\Event;
use Illuminate\Console\Command;

class CalculateEventPoints extends Command
{
    protected $signature = 'points:calculate
        {event? : The event ID to calculate points for}
        {--all-pending : Recalculate all completed events that have no points stored yet}';

    protected $description = 'Calculate and persist fantasy points for a completed event';

    public function handle(): int
    {
        if ($this->argument('event')) {
            $event = Event::findOrFail((int) $this->argument('event'));

            $this->dispatchForEvent($event);

            return Command::SUCCESS;
        }

        if ($this->option('all-pending')) {
            $events = Event::where('status', 'completed')
                ->whereDoesntHave('fantasyPoints')
                ->orderBy('scheduled_at')
                ->get();

            if ($events->isEmpty()) {
                $this->info('No pending completed events found.');

                return Command::SUCCESS;
            }

            foreach ($events as $event) {
                $this->dispatchForEvent($event);
            }

            return Command::SUCCESS;
        }

        $this->error('Provide an event ID or use --all-pending.');

        return Command::FAILURE;
    }

    protected function dispatchForEvent(Event $event): void
    {
        $this->info("Queuing points calculation for: {$event->name} (ID {$event->id})...");

        CalculateEventPointsJob::dispatch($event);
    }
}
