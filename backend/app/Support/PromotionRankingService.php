<?php

namespace App\Support;

use App\Models\MatchPrediction;
use App\Models\PromoWinner;
use App\Models\RegisteredInvoice;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PromotionRankingService
{
    public const WINNER_SLOTS = 10;

    public function __construct(
        private readonly ContestRules $contestRules,
    ) {
    }

    public function winnerSlots(): int
    {
        return $this->contestRules->winnerSlots() ?: self::WINNER_SLOTS;
    }

    public function leaderboardForPhase(int $phaseId, ?int $limit = null): Collection
    {
        $limit ??= $this->winnerSlots();
        $deliveredPrizeUserIds = PromoWinner::query()
            ->whereIn('status', ['delivered'])
            ->orWhereNotNull('prize_delivered_at')
            ->pluck('user_id')
            ->all();

        $predictionTotals = MatchPrediction::query()
            ->selectRaw("
                user_id,
                SUM(points_awarded) as prediction_points,
                SUM(CASE WHEN result_type = 'exact' THEN 1 ELSE 0 END) as exact_hits
            ")
            ->where('phase_id', $phaseId)
            ->groupBy('user_id');

        $invoiceTotals = RegisteredInvoice::query()
            ->selectRaw("
                user_id,
                SUM(points_awarded) as invoice_points,
                COUNT(*) as invoice_count,
                SUM(purchase_amount) as invoice_total_amount
            ")
            ->where('validation_status', 'approved')
            ->groupBy('user_id');

        $actualGoals = (int) TournamentMatch::query()
            ->where('phase_id', $phaseId)
            ->where('status', 'final')
            ->sum(DB::raw('COALESCE(home_score, 0) + COALESCE(away_score, 0)'));

        return DB::table('users')
            ->leftJoinSub($predictionTotals, 'prediction_totals', fn ($join) => $join->on('users.id', '=', 'prediction_totals.user_id'))
            ->leftJoinSub($invoiceTotals, 'invoice_totals', fn ($join) => $join->on('users.id', '=', 'invoice_totals.user_id'))
            ->where('users.role', 'client')
            ->whereNull('users.disqualified_at')
            ->when($deliveredPrizeUserIds !== [], fn ($query) => $query->whereNotIn('users.id', $deliveredPrizeUserIds))
            ->selectRaw("
                users.id,
                users.name,
                users.email,
                users.phone,
                users.group_stage_goal_prediction,
                users.registration_completed_at,
                users.registration_order_key,
                users.created_at,
                COALESCE(prediction_totals.prediction_points, 0) + COALESCE(invoice_totals.invoice_points, 0) as total_points,
                COALESCE(prediction_totals.exact_hits, 0) as exact_hits,
                COALESCE(invoice_totals.invoice_count, 0) as invoice_count,
                COALESCE(invoice_totals.invoice_total_amount, 0) as invoice_total_amount
            ")
            ->get()
            ->map(function ($row) use ($actualGoals) {
                $goalPrediction = $row->group_stage_goal_prediction !== null ? (int) $row->group_stage_goal_prediction : null;
                $goalPredictionDelta = $goalPrediction !== null ? abs($goalPrediction - $actualGoals) : PHP_INT_MAX;
                $rankingTimestamp = $row->registration_completed_at ?? $row->created_at;
                $rankingOrderKey = $row->registration_order_key ?: (string) ($rankingTimestamp ?? $row->created_at);

                return [
                    'user_id' => (int) $row->id,
                    'full_name' => $row->name,
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'goals' => (float) $row->total_points,
                    'total_points' => (float) $row->total_points,
                    'exact_hits' => (int) $row->exact_hits,
                    'invoice_count' => (int) $row->invoice_count,
                    'invoice_total_amount' => (float) $row->invoice_total_amount,
                    'group_stage_goal_prediction' => $goalPrediction,
                    'goal_prediction_delta' => $goalPredictionDelta,
                    'ranking_timestamp' => $rankingTimestamp,
                    'ranking_order_key' => $rankingOrderKey,
                ];
            })
            ->sort(function (array $left, array $right) {
                foreach ([
                    $right['total_points'] <=> $left['total_points'],
                    $right['exact_hits'] <=> $left['exact_hits'],
                    $right['invoice_count'] <=> $left['invoice_count'],
                    $right['invoice_total_amount'] <=> $left['invoice_total_amount'],
                    $left['goal_prediction_delta'] <=> $right['goal_prediction_delta'],
                    strcmp((string) $left['ranking_order_key'], (string) $right['ranking_order_key']),
                ] as $comparison) {
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            })
            ->values()
            ->take($limit)
            ->map(function (array $row, int $index) {
                $row['position'] = $index + 1;
                $row['football_role'] = $this->footballRole($index);

                return $row;
            });
    }

    public function tieContextForPhase(int $phaseId, int $slots = null, array $excludedUserIds = []): array
    {
        $slots ??= $this->winnerSlots();

        $rows = $this->leaderboardForPhase($phaseId)
            ->reject(fn (array $row) => in_array($row['user_id'], $excludedUserIds, true))
            ->values();

        if ($rows->count() <= $slots || $slots <= 0) {
            return [
                'requires_draw' => false,
                'auto_selected' => $rows->take($slots)->values()->all(),
                'tied_candidates' => [],
                'remaining_slots' => 0,
                'leaderboard' => $rows->all(),
            ];
        }

        $cutoff = $rows[$slots - 1];
        $next = $rows[$slots];

        if (! $this->sameTieMetrics($cutoff, $next)) {
            return [
                'requires_draw' => false,
                'auto_selected' => $rows->take($slots)->values()->all(),
                'tied_candidates' => [],
                'remaining_slots' => 0,
                'leaderboard' => $rows->all(),
            ];
        }

        $start = $slots - 1;
        while ($start > 0 && $this->sameTieMetrics($rows[$start - 1], $cutoff)) {
            $start--;
        }

        $end = $slots;
        while ($end + 1 < $rows->count() && $this->sameTieMetrics($rows[$end + 1], $cutoff)) {
            $end++;
        }

        return [
            'requires_draw' => true,
            'auto_selected' => $rows->slice(0, $start)->values()->all(),
            'tied_candidates' => $rows->slice($start, $end - $start + 1)->values()->all(),
            'remaining_slots' => $slots - $start,
            'leaderboard' => $rows->all(),
        ];
    }

    public function activeRankingPhase(?int $phaseId = null): ?TournamentPhase
    {
        if ($phaseId) {
            return TournamentPhase::query()
                ->whereKey($phaseId)
                ->where('is_active', true)
                ->first();
        }

        return TournamentPhase::query()
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN ? BETWEEN starts_at AND ends_at THEN 0 ELSE 1 END', [now()])
            ->orderBy('stage_order')
            ->first();
    }

    public function nextEligibleCandidate(int $phaseId, array $excludedUserIds): ?array
    {
        return $this->leaderboardForPhase($phaseId, 1000)
            ->first(fn (array $row) => ! in_array($row['user_id'], $excludedUserIds, true));
    }

    private function sameTieMetrics(array $left, array $right): bool
    {
        return $left['total_points'] === $right['total_points']
            && $left['exact_hits'] === $right['exact_hits']
            && $left['invoice_count'] === $right['invoice_count']
            && $left['invoice_total_amount'] === $right['invoice_total_amount']
            && $left['goal_prediction_delta'] === $right['goal_prediction_delta']
            && (string) $left['ranking_order_key'] === (string) $right['ranking_order_key'];
    }

    private function footballRole(int $index): string
    {
        return match (true) {
            $index === 0 => 'Goleador Estrella',
            $index === 1 => 'Medio Campo',
            $index === 2 => 'Defensa Central',
            $index === 3 => 'Portero',
            $index >= 4 && $index <= 19 => 'La Banca',
            default => 'Prelista de Convocados',
        };
    }
}
