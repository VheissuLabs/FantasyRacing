<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('constructors', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('franchise_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('constructors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
