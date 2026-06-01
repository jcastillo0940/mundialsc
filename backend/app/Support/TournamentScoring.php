<?php

namespace App\Support;

use App\Models\MatchPrediction;
use App\Models\TournamentMatch;

class TournamentScoring
{
    public function recalculateForMatch(TournamentMatch $match): void
    {
        $match->loadMissing('phase', 'predictions');

        foreach ($match->predictions as $prediction) {
            [$points, $resultType] = $this->scorePrediction(
                (int) $prediction->predicted_home_score,
                (int) $prediction->predicted_away_score,
                (int) $match->home_score,
                (int) $match->away_score,
                (int) $match->phase->exact_score_points,
                (int) $match->phase->outcome_points,
                $match->favorite_side,
                $match->phase->slug,
            );

            $prediction->update([
                'points_awarded' => $points,
                'result_type' => $resultType,
            ]);
        }
    }

    public function scorePrediction(
        int $predictedHome,
        int $predictedAway,
        int $actualHome,
        int $actualAway,
        int $exactPoints,
        int $outcomePoints,
        string $favoriteSide = 'none',
        ?string $phaseSlug = null,
    ): array {
        if ($phaseSlug === 'fase-grupos') {
            return $this->scoreGroupStagePrediction(
                $predictedHome,
                $predictedAway,
                $actualHome,
                $actualAway,
                $favoriteSide,
            );
        }

        if ($predictedHome === $actualHome && $predictedAway === $actualAway) {
            return [$exactPoints, 'exact'];
        }

        if ($this->outcome($predictedHome, $predictedAway) === $this->outcome($actualHome, $actualAway)) {
            return [$outcomePoints, 'outcome'];
        }

        return [0, 'miss'];
    }

    private function scoreGroupStagePrediction(
        int $predictedHome,
        int $predictedAway,
        int $actualHome,
        int $actualAway,
        string $favoriteSide,
    ): array {
        $predictedOutcome = $this->outcome($predictedHome, $predictedAway);
        $actualOutcome = $this->outcome($actualHome, $actualAway);

        if ($predictedOutcome !== $actualOutcome) {
            return [0, 'miss'];
        }

        $points = match ($actualOutcome) {
            'draw' => 2,
            'home', 'away' => $favoriteSide === 'none'
                ? throw new \RuntimeException('No se puede calcular puntos sin ranking FIFA oficial para definir favorito.')
                : ($actualOutcome === $favoriteSide ? 1 : 3),
            default => 0,
        };

        if ($predictedHome === $actualHome && $predictedAway === $actualAway) {
            return [$points + 3, 'exact'];
        }

        return [$points, 'outcome'];
    }

    private function outcome(int $home, int $away): string
    {
        if ($home === $away) {
            return 'draw';
        }

        return $home > $away ? 'home' : 'away';
    }
}
