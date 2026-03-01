<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_season_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('constructor_id')->constrained()->cascadeOnDelete();
            $table->integer('races_entered')->default(0);
            $table->integer('races_classified')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('podiums')->default(0);
            $table->integer('poles')->default(0);
            $table->integer('fastest_laps')->default(0);
            $table->integer('dnfs')->default(0);
            $table->integer('sprint_wins')->default(0);
            $table->integer('sprint_podiums')->default(0);
            $table->integer('sprint_fastest_laps')->default(0);
            $table->decimal('points_total', 8, 2)->default(0);
            $table->integer('best_finish')->nullable();
            $table->integer('championship_position')->nullable();
            $table->decimal('fantasy_points_total', 8, 2)->default(0);
            $table->decimal('fantasy_ownership_pct', 5, 2)->nullable();
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();
            $table->unique(['driver_id', 'season_id']);
        });

        Schema::create('constructor_season_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constructor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->integer('races_entered')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('podiums')->default(0);
            $table->integer('one_twos')->default(0);
            $table->integer('poles')->default(0);
            $table->integer('fastest_laps')->default(0);
            $table->integer('sprint_wins')->default(0);
            $table->integer('sprint_podiums')->default(0);
            $table->integer('sprint_one_twos')->default(0);
            $table->decimal('points_total', 8, 2)->default(0);
            $table->integer('best_finish')->nullable();
            $table->integer('championship_position')->nullable();
            $table->decimal('fantasy_points_total', 8, 2)->default(0);
            $table->decimal('fantasy_ownership_pct', 5, 2)->nullable();
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();
            $table->unique(['constructor_id', 'season_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constructor_season_stats');
        Schema::dropIfExists('driver_season_stats');
    }
};
