<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fantasy_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['league_id', 'user_id']);
        });

        Schema::create('fantasy_team_roster', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fantasy_team_id')->constrained('fantasy_teams')->cascadeOnDelete();
            $table->enum('entity_type', ['driver', 'constructor']);
            $table->unsignedBigInteger('entity_id');
            $table->boolean('in_seat')->default(false);
            $table->timestamp('acquired_at');
            $table->timestamps();
            $table->unique(['fantasy_team_id', 'entity_type', 'entity_id']);
        });

        Schema::create('fantasy_event_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fantasy_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->enum('entity_type', ['driver', 'constructor']);
            $table->unsignedBigInteger('entity_id');
            $table->decimal('points', 8, 2);
            $table->jsonb('breakdown');
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['fantasy_team_id', 'event_id', 'entity_type', 'entity_id']);
        });

        Schema::create('free_agent_pool', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->enum('entity_type', ['driver', 'constructor']);
            $table->unsignedBigInteger('entity_id');
            $table->timestamp('added_at');
            $table->timestamps();
            $table->unique(['league_id', 'entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_agent_pool');
        Schema::dropIfExists('fantasy_event_points');
        Schema::dropIfExists('fantasy_team_roster');
        Schema::dropIfExists('fantasy_teams');
    }
};
