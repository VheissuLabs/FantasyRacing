<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('year');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('location');
            $table->string('country');
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('constructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('jolpica_constructor_id')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['franchise_id', 'slug']);
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['franchise_id', 'slug']);
        });

        Schema::create('season_constructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('constructor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['season_id', 'constructor_id']);
        });

        Schema::create('season_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('constructor_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['race', 'qualifying', 'sprint', 'sprint_qualifying', 'practice']);
            $table->timestamp('scheduled_at');
            $table->timestamp('locked_at')->nullable();
            $table->enum('status', ['scheduled', 'locked', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->integer('sort_order');
            $table->unsignedSmallInteger('round')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('constructor_id')->constrained()->cascadeOnDelete();
            $table->integer('finish_position')->nullable();
            $table->integer('grid_position')->nullable();
            $table->enum('status', ['classified', 'dnf', 'dns', 'dsq'])->default('classified');
            $table->boolean('fastest_lap')->default(false);
            $table->boolean('driver_of_the_day')->default(false);
            $table->integer('overtakes_made')->nullable();
            $table->time('q1_time')->nullable();
            $table->time('q2_time')->nullable();
            $table->time('q3_time')->nullable();
            $table->boolean('teammate_outqualified')->nullable();
            $table->boolean('points_eligible')->default(true);
            $table->enum('data_source', ['manual', 'jolpica'])->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'driver_id']);
        });

        Schema::create('event_pitstops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('constructor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->integer('stop_lap')->nullable();
            $table->decimal('stop_time_seconds', 6, 3);
            $table->boolean('is_fastest_of_event')->default(false);
            $table->enum('data_source', ['manual', 'jolpica'])->default('manual');
            $table->timestamps();
            $table->index(['event_id', 'constructor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_pitstops');
        Schema::dropIfExists('event_results');
        Schema::dropIfExists('events');
        Schema::dropIfExists('season_drivers');
        Schema::dropIfExists('season_constructors');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('constructors');
        Schema::dropIfExists('tracks');
        Schema::dropIfExists('seasons');
    }
};
