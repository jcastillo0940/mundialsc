<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tournament_phases')) {
            return;
        }

        Schema::table('tournament_phases', function (Blueprint $table): void {
            if (! Schema::hasColumn('tournament_phases', 'contest_round')) {
                $table->string('contest_round', 40)->default('group_stage')->after('slug');
            }
        });

        DB::table('tournament_phases')
            ->where('slug', 'fase-grupos')
            ->update(['contest_round' => 'group_stage', 'updated_at' => now()]);

        DB::table('tournament_phases')
            ->whereIn('slug', ['dieciseisavos', 'octavos', 'cuartos', 'semifinal-final'])
            ->update(['contest_round' => 'knockout', 'updated_at' => now()]);

        DB::table('tournament_phases')->updateOrInsert(
            ['slug' => 'semifinal'],
            [
                'name' => 'Semifinal',
                'contest_round' => 'knockout',
                'stage_order' => 5,
                'starts_at' => '2026-07-18 00:00:00',
                'ends_at' => '2026-07-19 23:59:59',
                'exact_score_points' => 7,
                'outcome_points' => 3,
                'reset_phase_table' => false,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('tournament_phases')->updateOrInsert(
            ['slug' => 'final'],
            [
                'name' => 'Final',
                'contest_round' => 'knockout',
                'stage_order' => 6,
                'starts_at' => '2026-07-20 00:00:00',
                'ends_at' => '2026-07-20 23:59:59',
                'exact_score_points' => 7,
                'outcome_points' => 3,
                'reset_phase_table' => false,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $finalPhaseId = DB::table('tournament_phases')->where('slug', 'final')->value('id');

        if ($finalPhaseId && Schema::hasTable('phase_prizes')) {
            DB::table('phase_prizes')->updateOrInsert(
                [
                    'phase_id' => $finalPhaseId,
                    'ranking_from' => 1,
                    'ranking_to' => 20,
                    'prize_type' => 'bono_200',
                ],
                [
                    'football_role' => 'Ganadores Fase Eliminatoria',
                    'prize_title' => 'Bono Super Carnes USD 200',
                    'stock' => 20,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tournament_phases')) {
            return;
        }

        DB::table('tournament_phases')
            ->whereIn('slug', ['semifinal', 'final'])
            ->delete();

        Schema::table('tournament_phases', function (Blueprint $table): void {
            if (Schema::hasColumn('tournament_phases', 'contest_round')) {
                $table->dropColumn('contest_round');
            }
        });
    }
};
