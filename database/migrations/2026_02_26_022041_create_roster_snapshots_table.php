<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fantasy_team_id')->constrained()->cascadeOnDelete();
            $table->jsonb('snapshot');
            $table->timestamps();
            $table->unique(['event_id', 'fantasy_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_snapshots');
    }
};
