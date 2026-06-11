<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_score_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('live_score_settings', 'fixtures_sync_interval_hours')) {
                $table->unsignedSmallInteger('fixtures_sync_interval_hours')->default(24)->after('auto_sync_commentary');
            }

            if (! Schema::hasColumn('live_score_settings', 'live_sync_interval_minutes')) {
                $table->unsignedSmallInteger('live_sync_interval_minutes')->default(3)->after('fixtures_sync_interval_hours');
            }

            if (! Schema::hasColumn('live_score_settings', 'commentary_sync_interval_minutes')) {
                $table->unsignedSmallInteger('commentary_sync_interval_minutes')->default(3)->after('live_sync_interval_minutes');
            }
        });

        DB::table('live_score_settings')
            ->whereNull('fixtures_sync_interval_hours')
            ->update(['fixtures_sync_interval_hours' => 24]);

        DB::table('live_score_settings')
            ->whereNull('live_sync_interval_minutes')
            ->update(['live_sync_interval_minutes' => 3]);

        DB::table('live_score_settings')
            ->whereNull('commentary_sync_interval_minutes')
            ->update(['commentary_sync_interval_minutes' => 3]);
    }

    public function down(): void
    {
        Schema::table('live_score_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('live_score_settings', 'commentary_sync_interval_minutes')) {
                $table->dropColumn('commentary_sync_interval_minutes');
            }

            if (Schema::hasColumn('live_score_settings', 'live_sync_interval_minutes')) {
                $table->dropColumn('live_sync_interval_minutes');
            }

            if (Schema::hasColumn('live_score_settings', 'fixtures_sync_interval_hours')) {
                $table->dropColumn('fixtures_sync_interval_hours');
            }
        });
    }
};
