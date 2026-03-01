<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiator_team_id')->constrained('fantasy_teams')->cascadeOnDelete();
            $table->foreignId('receiver_team_id')->nullable()->constrained('fantasy_teams')->nullOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled', 'completed'])->default('pending');
            $table->timestamp('initiated_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('trade_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_team_id')->nullable()->constrained('fantasy_teams')->nullOnDelete();
            $table->foreignId('to_team_id')->nullable()->constrained('fantasy_teams')->nullOnDelete();
            $table->enum('entity_type', ['driver', 'constructor']);
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_items');
        Schema::dropIfExists('trades');
    }
};
