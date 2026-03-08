<?php

use App\Models\BonusPointsScheme;
use App\Models\Franchise;
use App\Models\PointsScheme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->integer('position');
            $table->decimal('points', 6, 2);
            $table->timestamps();
            $table->unique(['franchise_id', 'event_type', 'position']);
        });

        Schema::create('bonus_points_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('bonus_key');
            $table->enum('applies_to', ['driver', 'constructor']);
            $table->decimal('points', 6, 2);
            $table->timestamps();
            $table->unique(['franchise_id', 'event_type', 'bonus_key']);
        });

        $this->seedF1PointsScheme();
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_points_schemes');
        Schema::dropIfExists('points_schemes');
    }

    private function seedF1PointsScheme(): void
    {
        $franchise = Franchise::firstOrCreate(
            ['slug' => 'f1'],
            ['name' => 'Formula 1', 'description' => 'Formula 1 World Championship', 'is_active' => true],
        );

        $racePoints = [
            1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10,
            6 => 8, 7 => 6, 8 => 4, 9 => 2, 10 => 1, 11 => 0,
        ];

        foreach ($racePoints as $position => $points) {
            PointsScheme::updateOrCreate(
                ['franchise_id' => $franchise->id, 'event_type' => 'race', 'position' => $position],
                ['points' => $points],
            );
        }

        $qualifyingPoints = [
            1 => 10, 2 => 9, 3 => 8, 4 => 7, 5 => 6,
            6 => 5, 7 => 4, 8 => 3, 9 => 2, 10 => 1,
        ];

        foreach ($qualifyingPoints as $position => $points) {
            PointsScheme::updateOrCreate(
                ['franchise_id' => $franchise->id, 'event_type' => 'qualifying', 'position' => $position],
                ['points' => $points],
            );
        }

        $sprintPoints = [1 => 8, 2 => 7, 3 => 6, 4 => 5, 5 => 4, 6 => 3, 7 => 2, 8 => 1];

        foreach ($sprintPoints as $position => $points) {
            PointsScheme::updateOrCreate(
                ['franchise_id' => $franchise->id, 'event_type' => 'sprint', 'position' => $position],
                ['points' => $points],
            );
        }

        $bonuses = [
            ['event_type' => 'race', 'bonus_key' => 'fastest_lap', 'applies_to' => 'driver', 'points' => 10],
            ['event_type' => 'race', 'bonus_key' => 'driver_of_the_day', 'applies_to' => 'driver', 'points' => 10],
            ['event_type' => 'race', 'bonus_key' => 'dnf_penalty', 'applies_to' => 'driver', 'points' => -20],
            ['event_type' => 'race', 'bonus_key' => 'positions_gained', 'applies_to' => 'driver', 'points' => 1],
            ['event_type' => 'race', 'bonus_key' => 'positions_lost', 'applies_to' => 'driver', 'points' => -1],
            ['event_type' => 'race', 'bonus_key' => 'overtake', 'applies_to' => 'driver', 'points' => 1],
            ['event_type' => 'qualifying', 'bonus_key' => 'nc_dsq_penalty', 'applies_to' => 'driver', 'points' => -5],
            ['event_type' => 'sprint', 'bonus_key' => 'fastest_lap', 'applies_to' => 'driver', 'points' => 5],
            ['event_type' => 'sprint', 'bonus_key' => 'dnf_penalty', 'applies_to' => 'driver', 'points' => -10],
            ['event_type' => 'sprint', 'bonus_key' => 'positions_gained', 'applies_to' => 'driver', 'points' => 1],
            ['event_type' => 'sprint', 'bonus_key' => 'positions_lost', 'applies_to' => 'driver', 'points' => -1],
            ['event_type' => 'sprint', 'bonus_key' => 'overtake', 'applies_to' => 'driver', 'points' => 1],
            ['event_type' => 'race', 'bonus_key' => 'constructor_dsq', 'applies_to' => 'constructor', 'points' => -20],
            ['event_type' => 'race', 'bonus_key' => 'pitstop_fastest', 'applies_to' => 'constructor', 'points' => 5],
            ['event_type' => 'race', 'bonus_key' => 'pitstop_under_2s', 'applies_to' => 'constructor', 'points' => 10],
            ['event_type' => 'race', 'bonus_key' => 'pitstop_2s_2.19s', 'applies_to' => 'constructor', 'points' => 10],
            ['event_type' => 'race', 'bonus_key' => 'pitstop_2.2s_2.49s', 'applies_to' => 'constructor', 'points' => 5],
            ['event_type' => 'race', 'bonus_key' => 'pitstop_2.5s_2.99s', 'applies_to' => 'constructor', 'points' => 2],
            ['event_type' => 'qualifying', 'bonus_key' => 'constructor_neither_reaches_q2', 'applies_to' => 'constructor', 'points' => -1],
            ['event_type' => 'qualifying', 'bonus_key' => 'constructor_one_reaches_q2', 'applies_to' => 'constructor', 'points' => 1],
            ['event_type' => 'qualifying', 'bonus_key' => 'constructor_both_reaches_q2', 'applies_to' => 'constructor', 'points' => 5],
            ['event_type' => 'qualifying', 'bonus_key' => 'constructor_one_reaches_q3', 'applies_to' => 'constructor', 'points' => 3],
            ['event_type' => 'qualifying', 'bonus_key' => 'constructor_both_reaches_q3', 'applies_to' => 'constructor', 'points' => 10],
            ['event_type' => 'qualifying', 'bonus_key' => 'constructor_dsq', 'applies_to' => 'constructor', 'points' => -5],
            ['event_type' => 'sprint', 'bonus_key' => 'constructor_dsq', 'applies_to' => 'constructor', 'points' => -10],
        ];

        foreach ($bonuses as $bonus) {
            BonusPointsScheme::updateOrCreate(
                ['franchise_id' => $franchise->id, 'event_type' => $bonus['event_type'], 'bonus_key' => $bonus['bonus_key']],
                ['applies_to' => $bonus['applies_to'], 'points' => $bonus['points']],
            );
        }
    }
};
