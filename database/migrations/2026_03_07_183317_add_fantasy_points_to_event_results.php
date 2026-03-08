<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_results', function (Blueprint $table) {
            $table->decimal('fantasy_points', 8, 2)->nullable()->after('points_eligible');
            $table->jsonb('fantasy_breakdown')->nullable()->after('fantasy_points');
        });
    }

    public function down(): void
    {
        Schema::table('event_results', function (Blueprint $table) {
            $table->dropColumn(['fantasy_points', 'fantasy_breakdown']);
        });
    }
};
