<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete()->unique();
            $table->enum('type', ['snake', 'linear']);
            $table->integer('pick_time_limit_seconds')->default(60);
            $table->enum('status', ['pending', 'active', 'paused', 'completed'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('current_pick_number')->default(0);
            $table->integer('total_picks');
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('draft_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_session_id')->constrained()->cascadeOnDelete();
            $table->integer('pick_number');
            $table->integer('round');
            $table->integer('round_pick');
            $table->foreignId('fantasy_team_id')->constrained()->cascadeOnDelete();
            $table->enum('entity_type_restriction', ['constructor', 'driver']);
            $table->timestamps();
            $table->unique(['draft_session_id', 'pick_number']);
            $table->unique(['draft_session_id', 'round', 'fantasy_team_id']);
        });

        Schema::create('draft_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draft_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fantasy_team_id')->constrained()->cascadeOnDelete();
            $table->integer('pick_number');
            $table->enum('entity_type', ['driver', 'constructor']);
            $table->unsignedBigInteger('entity_id');
            $table->boolean('is_auto_pick')->default(false);
            $table->timestamp('picked_at');
            $table->timestamps();
            $table->unique(['draft_session_id', 'pick_number']);
            $table->unique(['draft_session_id', 'entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_picks');
        Schema::dropIfExists('draft_orders');
        Schema::dropIfExists('draft_sessions');
    }
};
