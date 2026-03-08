<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE event_results DROP CONSTRAINT event_results_status_check');
        DB::statement("ALTER TABLE event_results ADD CONSTRAINT event_results_status_check CHECK (status IN ('classified', 'dnf', 'dns', 'dsq', 'not_classified'))");

        DB::statement('ALTER TABLE event_results DROP CONSTRAINT event_results_data_source_check');
        DB::statement("ALTER TABLE event_results ADD CONSTRAINT event_results_data_source_check CHECK (data_source IN ('manual', 'jolpica', 'openf1', 'derived'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE event_results DROP CONSTRAINT event_results_status_check');
        DB::statement("ALTER TABLE event_results ADD CONSTRAINT event_results_status_check CHECK (status IN ('classified', 'dnf', 'dns', 'dsq'))");

        DB::statement('ALTER TABLE event_results DROP CONSTRAINT event_results_data_source_check');
        DB::statement("ALTER TABLE event_results ADD CONSTRAINT event_results_data_source_check CHECK (data_source IN ('manual', 'jolpica', 'openf1'))");
    }
};
