<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            if (! Schema::hasColumn('teams', 'external_country_id')) {
                $table->unsignedBigInteger('external_country_id')->nullable()->after('external_team_id');
            }
            if (! Schema::hasColumn('teams', 'provider_logo_url')) {
                $table->string('provider_logo_url', 255)->nullable()->after('flag_emoji');
            }
            if (! Schema::hasColumn('teams', 'provider_flag_path')) {
                $table->string('provider_flag_path', 120)->nullable()->after('provider_logo_url');
            }
        });

        Schema::table('tournament_matches', function (Blueprint $table): void {
            if (! Schema::hasColumn('tournament_matches', 'external_group_id')) {
                $table->unsignedBigInteger('external_group_id')->nullable()->after('external_match_id');
                $table->index('external_group_id', 'idx_tournament_matches_external_group_id');
            }
            if (! Schema::hasColumn('tournament_matches', 'round_label')) {
                $table->string('round_label', 80)->nullable()->after('group_label');
            }
            if (! Schema::hasColumn('tournament_matches', 'stage_label')) {
                $table->string('stage_label', 120)->nullable()->after('round_label');
            }
            if (! Schema::hasColumn('tournament_matches', 'venue_name')) {
                $table->string('venue_name', 180)->nullable()->after('stage_label');
            }
            if (! Schema::hasColumn('tournament_matches', 'provider_competition_name')) {
                $table->string('provider_competition_name', 180)->nullable()->after('provider_status');
            }
            if (! Schema::hasColumn('tournament_matches', 'kickoff_timezone')) {
                $table->string('kickoff_timezone', 40)->nullable()->after('provider_competition_name');
            }
        });

        Schema::table('live_score_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('live_score_settings', 'season')) {
                $table->string('season', 20)->nullable()->after('competition_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_score_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('live_score_settings', 'season')) {
                $table->dropColumn('season');
            }
        });

        Schema::table('tournament_matches', function (Blueprint $table): void {
            if (Schema::hasColumn('tournament_matches', 'kickoff_timezone')) {
                $table->dropColumn('kickoff_timezone');
            }
            if (Schema::hasColumn('tournament_matches', 'provider_competition_name')) {
                $table->dropColumn('provider_competition_name');
            }
            if (Schema::hasColumn('tournament_matches', 'venue_name')) {
                $table->dropColumn('venue_name');
            }
            if (Schema::hasColumn('tournament_matches', 'stage_label')) {
                $table->dropColumn('stage_label');
            }
            if (Schema::hasColumn('tournament_matches', 'round_label')) {
                $table->dropColumn('round_label');
            }
            if (Schema::hasColumn('tournament_matches', 'external_group_id')) {
                $table->dropIndex('idx_tournament_matches_external_group_id');
                $table->dropColumn('external_group_id');
            }
        });

        Schema::table('teams', function (Blueprint $table): void {
            if (Schema::hasColumn('teams', 'provider_flag_path')) {
                $table->dropColumn('provider_flag_path');
            }
            if (Schema::hasColumn('teams', 'provider_logo_url')) {
                $table->dropColumn('provider_logo_url');
            }
            if (Schema::hasColumn('teams', 'external_country_id')) {
                $table->dropColumn('external_country_id');
            }
        });
    }
};
