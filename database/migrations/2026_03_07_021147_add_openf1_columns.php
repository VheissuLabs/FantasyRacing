<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->integer('openf1_session_key')->nullable()->after('round');
        });

        DB::statement('ALTER TABLE event_results DROP CONSTRAINT event_results_data_source_check');
        DB::statement("ALTER TABLE event_results ADD CONSTRAINT event_results_data_source_check CHECK ((data_source)::text = ANY ((ARRAY['manual'::character varying, 'jolpica'::character varying, 'openf1'::character varying])::text[]))");

        DB::statement('ALTER TABLE event_pitstops DROP CONSTRAINT event_pitstops_data_source_check');
        DB::statement("ALTER TABLE event_pitstops ADD CONSTRAINT event_pitstops_data_source_check CHECK ((data_source)::text = ANY ((ARRAY['manual'::character varying, 'jolpica'::character varying, 'openf1'::character varying])::text[]))");
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('openf1_session_key');
        });

        DB::statement('ALTER TABLE event_results DROP CONSTRAINT event_results_data_source_check');
        DB::statement("ALTER TABLE event_results ADD CONSTRAINT event_results_data_source_check CHECK ((data_source)::text = ANY ((ARRAY['manual'::character varying, 'jolpica'::character varying])::text[]))");

        DB::statement('ALTER TABLE event_pitstops DROP CONSTRAINT event_pitstops_data_source_check');
        DB::statement("ALTER TABLE event_pitstops ADD CONSTRAINT event_pitstops_data_source_check CHECK ((data_source)::text = ANY ((ARRAY['manual'::character varying, 'jolpica'::character varying])::text[]))");
    }
};
