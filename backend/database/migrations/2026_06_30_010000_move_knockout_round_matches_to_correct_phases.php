<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roundToPhaseSlug = [
            'R32' => 'dieciseisavos',
            'R16' => 'octavos',
            'QF' => 'cuartos',
            'SF' => 'semifinal',
            '3PPO' => 'final',
            'F' => 'final',
        ];

        $phaseIds = DB::table('tournament_phases')
            ->whereIn('slug', array_values($roundToPhaseSlug))
            ->pluck('id', 'slug');

        foreach ($roundToPhaseSlug as $roundLabel => $phaseSlug) {
            $phaseId = $phaseIds[$phaseSlug] ?? null;

            if (! $phaseId) {
                continue;
            }

            do {
                $matchIds = DB::table('tournament_matches')
                    ->where('round_label', $roundLabel)
                    ->where('phase_id', '!=', $phaseId)
                    ->orderBy('id')
                    ->limit(500)
                    ->pluck('id');

                if ($matchIds->isEmpty()) {
                    break;
                }

                DB::table('tournament_matches')
                    ->whereIn('id', $matchIds)
                    ->update([
                        'phase_id' => $phaseId,
                        'updated_at' => now(),
                    ]);

                DB::table('match_predictions')
                    ->whereIn('match_id', $matchIds)
                    ->update([
                        'phase_id' => $phaseId,
                        'updated_at' => now(),
                    ]);
            } while ($matchIds->isNotEmpty());
        }
    }

    public function down(): void
    {
        //
    }
};
