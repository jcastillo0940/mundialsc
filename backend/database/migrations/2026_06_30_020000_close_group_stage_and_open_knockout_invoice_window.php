<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tournament_phases') || ! Schema::hasTable('tournament_matches')) {
            return;
        }

        $cutoff = $this->groupStageInvoiceCutoff();

        DB::table('tournament_phases')
            ->where('slug', 'fase-grupos')
            ->update([
                'ends_at' => $cutoff,
                'is_active' => true,
                'updated_at' => now(),
            ]);

        DB::table('tournament_phases')
            ->where('slug', 'dieciseisavos')
            ->update([
                'starts_at' => $cutoff,
                'is_active' => true,
                'updated_at' => now(),
            ]);

        DB::table('tournament_phases')
            ->whereIn('slug', ['octavos', 'cuartos', 'semifinal', 'final', 'semifinal-final'])
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);

        if (Schema::hasColumn('tournament_phases', 'contest_round')) {
            DB::table('tournament_phases')
                ->whereIn('slug', ['dieciseisavos', 'octavos', 'cuartos', 'semifinal', 'final', 'semifinal-final'])
                ->update([
                    'contest_round' => 'knockout',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tournament_phases')) {
            return;
        }

        DB::table('tournament_phases')
            ->where('slug', 'fase-grupos')
            ->update([
                'ends_at' => '2026-07-03 23:59:59',
                'is_active' => true,
                'updated_at' => now(),
            ]);

        DB::table('tournament_phases')
            ->where('slug', 'dieciseisavos')
            ->update([
                'starts_at' => '2026-07-04 00:00:00',
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    private function groupStageInvoiceCutoff(): string
    {
        $lastGroupKickoff = DB::table('tournament_matches')
            ->where('stage_label', 'Group Stage')
            ->whereNotNull('group_label')
            ->whereIn('round_label', ['1', '2', '3'])
            ->max('kickoff_at');

        return ($lastGroupKickoff
            ? Carbon::parse($lastGroupKickoff)->addHours(2)
            : Carbon::parse('2026-06-28 04:00:00')
        )->toDateTimeString();
    }
};
