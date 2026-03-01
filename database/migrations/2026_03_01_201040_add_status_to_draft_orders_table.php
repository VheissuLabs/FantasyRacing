<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('draft_orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending')->after('entity_type_restriction');
        });
    }

    public function down(): void
    {
        Schema::table('draft_orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
