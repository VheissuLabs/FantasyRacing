<?php

use App\Models\Event;
use App\Services\PointsCalculationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $franchise = DB::table('franchises')->where('slug', 'f1')->first();

        if (! $franchise) {
            return;
        }

        $newBonuses = [
            ['event_type' => 'race', 'bonus_key' => 'positions_gained', 'applies_to' => 'driver', 'points' => 1],
            ['event_type' => 'race', 'bonus_key' => 'positions_lost', 'applies_to' => 'driver', 'points' => -1],
            ['event_type' => 'race', 'bonus_key' => 'overtake', 'applies_to' => 'driver', 'points' => 1],
        ];

        foreach ($newBonuses as $bonus) {
            $exists = DB::table('bonus_points_schemes')
                ->where('franchise_id', $franchise->id)
                ->where('event_type', $bonus['event_type'])
                ->where('bonus_key', $bonus['bonus_key'])
                ->exists();

            if ($exists) {
                DB::table('bonus_points_schemes')
                    ->where('franchise_id', $franchise->id)
                    ->where('event_type', $bonus['event_type'])
                    ->where('bonus_key', $bonus['bonus_key'])
                    ->update(['points' => $bonus['points'], 'updated_at' => now()]);
            } else {
                DB::table('bonus_points_schemes')->insert([
                    'franchise_id' => $franchise->id,
                    'event_type' => $bonus['event_type'],
                    'bonus_key' => $bonus['bonus_key'],
                    'applies_to' => $bonus['applies_to'],
                    'points' => $bonus['points'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('bonus_points_schemes')
            ->where('franchise_id', $franchise->id)
            ->where('event_type', 'qualifying')
            ->where('bonus_key', 'constructor_both_reaches_q2')
            ->update(['points' => 5]);

        DB::table('bonus_points_schemes')
            ->where('franchise_id', $franchise->id)
            ->where('event_type', 'qualifying')
            ->where('bonus_key', 'constructor_one_reaches_q3')
            ->update(['points' => 3]);

        DB::table('bonus_points_schemes')
            ->where('franchise_id', $franchise->id)
            ->where('event_type', 'race')
            ->where('bonus_key', 'pitstop_under_2s')
            ->update(['points' => 10]);

        $calculator = app(PointsCalculationService::class);

        Event::where('status', 'completed')
            ->each(fn ($event) => $calculator->calculateForEvent($event));
    }
};
