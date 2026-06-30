<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        do {
            $rows = DB::table('match_predictions as predictions')
                ->join('tournament_matches as matches', 'matches.id', '=', 'predictions.match_id')
                ->whereColumn('predictions.phase_id', '!=', 'matches.phase_id')
                ->orderBy('predictions.id')
                ->limit(500)
                ->get(['predictions.id', 'matches.phase_id']);

            foreach ($rows as $row) {
                DB::table('match_predictions')
                    ->where('id', $row->id)
                    ->update([
                        'phase_id' => $row->phase_id,
                        'updated_at' => now(),
                    ]);
            }
        } while ($rows->isNotEmpty());
    }

    public function down(): void
    {
        //
    }
};
