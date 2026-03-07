<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_constructor_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('constructor_id')->constrained()->cascadeOnDelete();
            $table->decimal('fantasy_points', 8, 2)->default(0);
            $table->jsonb('fantasy_breakdown')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'constructor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_constructor_results');
    }
};
