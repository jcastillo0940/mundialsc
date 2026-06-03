<?php

namespace App\Support;

use App\Models\TournamentPhase;
use Carbon\CarbonInterface;

class TournamentPhaseResolver
{
    public function phaseForDate(?CarbonInterface $date): ?TournamentPhase
    {
        if (! $date) {
            return $this->currentPhase();
        }

        return TournamentPhase::query()
            ->where('starts_at', '<=', $date)
            ->where('ends_at', '>=', $date)
            ->orderBy('stage_order')
            ->first()
            ?? $this->currentPhase();
    }

    public function currentPhase(): ?TournamentPhase
    {
        return TournamentPhase::query()
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN ? BETWEEN starts_at AND ends_at THEN 0 ELSE 1 END', [now()])
            ->orderBy('stage_order')
            ->first();
    }
}
