<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_results', function (Blueprint $table) {
            $table->decimal('fia_points', 5, 2)->nullable()->after('fantasy_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('event_results', function (Blueprint $table) {
            $table->dropColumn('fia_points');
        });
    }
};
