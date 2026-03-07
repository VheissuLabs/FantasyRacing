<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_results', function (Blueprint $table) {
            $table->time('q1_time', precision: 3)->nullable()->change();
            $table->time('q2_time', precision: 3)->nullable()->change();
            $table->time('q3_time', precision: 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_results', function (Blueprint $table) {
            $table->time('q1_time', precision: 0)->nullable()->change();
            $table->time('q2_time', precision: 0)->nullable()->change();
            $table->time('q3_time', precision: 0)->nullable()->change();
        });
    }
};
