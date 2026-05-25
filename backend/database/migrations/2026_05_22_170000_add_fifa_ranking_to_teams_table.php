<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            if (! Schema::hasColumn('teams', 'ranking_fifa')) {
                $table->unsignedInteger('ranking_fifa')->nullable()->after('code');
                $table->index('ranking_fifa', 'idx_teams_ranking_fifa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            if (Schema::hasColumn('teams', 'ranking_fifa')) {
                $table->dropIndex('idx_teams_ranking_fifa');
                $table->dropColumn('ranking_fifa');
            }
        });
    }
};
