<?php

namespace App\Support;

use App\Models\MatchPrediction;
use App\Models\OnlineStoreOrder;
use App\Models\PhasePrize;
use App\Models\PromoWinner;
use App\Models\RegisteredInvoice;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PromotionRankingService
{
    public const WINNER_SLOTS = 20;
    private const KNOCKOUT_PHASE_SLUGS = [
        'dieciseisavos',
        'octavos',
        'cuartos',
        'semifinal',
        'final',
        'semifinal-final',
    ];

    public function __construct(
        private readonly ContestRules $contestRules,
    ) {
    }

    public function winnerSlots(): int
    {
        return $this->contestRules->winnerSlots() ?: self::WINNER_SLOTS;
    }

    public function winnerSlotsForPhase(int $phaseId): int
    {
        $max = (int) PhasePrize::where('phase_id', $phaseId)->max('ranking_to');

        return $max > 0 ? $max : $this->winnerSlots();
    }

    public function leaderboardForPhase(int $phaseId, ?int $limit = null): Collection
    {
        $limit ??= $this->winnerSlotsForPhase($phaseId);

        return $this->fullRankedLeaderboard($phaseId)->take($limit)->values();
    }

    public function prizeEligibleLeaderboardForPhase(int $phaseId, ?int $limit = null): Collection
    {
        $limit ??= $this->winnerSlotsForPhase($phaseId);
        $priorWinnerUserIds = $this->priorWinnerUserIds($phaseId);

        return $this->fullRankedLeaderboard($phaseId)
            ->reject(fn (array $row) => in_array($row['user_id'], $priorWinnerUserIds, true))
            ->take($limit)
            ->values();
    }

    public function fullRankedLeaderboard(int $phaseId): Collection
    {
        $phase = TournamentPhase::findOrFail($phaseId);
        $leaderboardPhaseIds = $this->leaderboardPhaseIds($phase);
        $priorWinnerUserIds = $this->priorWinnerUserIds($phaseId);

        $predictionTotals = MatchPrediction::query()
            ->selectRaw("
                user_id,
                SUM(points_awarded) as prediction_points,
                SUM(CASE WHEN result_type = 'exact' THEN 1 ELSE 0 END) as exact_hits
            ")
            ->whereIn('phase_id', $leaderboardPhaseIds)
            ->groupBy('user_id');

        // Count invoices by registration time, not by invoice issue date.
        $invoiceTotals = $this->constrainInvoiceQueryToPhase(
            RegisteredInvoice::query()
            ->selectRaw("
                user_id,
                SUM(points_awarded) as invoice_points,
                COUNT(*) as invoice_count,
                SUM(purchase_amount) as invoice_total_amount
            ")
            ->where('validation_status', 'approved'),
            $phase,
        )
            ->groupBy('user_id');

        $onlineStoreTotals = OnlineStoreOrder::query()
            ->selectRaw('user_id, SUM(points_awarded) as online_store_points')
            ->whereNotNull('user_id')
            ->whereNotNull('credited_at')
            ->where('points_awarded', '>', 0);

        if (! $this->isKnockoutPhase($phase)) {
            $onlineStoreTotals->whereRaw('1 = 0');
        }

        $onlineStoreTotals->groupBy('user_id');

        $actualGoals = (int) TournamentMatch::query()
            ->whereIn('phase_id', $leaderboardPhaseIds)
            ->where('status', 'final')
            ->sum(DB::raw('COALESCE(home_score, 0) + COALESCE(away_score, 0)'));

        return DB::table('users')
            ->leftJoinSub($predictionTotals, 'prediction_totals', fn ($join) => $join->on('users.id', '=', 'prediction_totals.user_id'))
            ->leftJoinSub($invoiceTotals, 'invoice_totals', fn ($join) => $join->on('users.id', '=', 'invoice_totals.user_id'))
            ->leftJoinSub($onlineStoreTotals, 'online_store_totals', fn ($join) => $join->on('users.id', '=', 'online_store_totals.user_id'))
            ->leftJoin('branches', 'branches.id', '=', 'users.branch_id')
            ->where('users.role', 'client')
            ->whereNull('users.disqualified_at')
            ->selectRaw("
                users.id,
                users.name,
                users.cedula,
                users.email,
                users.phone,
                users.group_stage_goal_prediction,
                users.registration_completed_at,
                users.registration_order_key,
                users.created_at,
                branches.name as branch_name,
                COALESCE(prediction_totals.prediction_points, 0) as prediction_points,
                COALESCE(invoice_totals.invoice_points, 0) as invoice_points,
                COALESCE(online_store_totals.online_store_points, 0) as online_store_points,
                COALESCE(prediction_totals.prediction_points, 0) + COALESCE(invoice_totals.invoice_points, 0) + COALESCE(online_store_totals.online_store_points, 0) as total_points,
                COALESCE(prediction_totals.exact_hits, 0) as exact_hits,
                COALESCE(invoice_totals.invoice_count, 0) as invoice_count,
                COALESCE(invoice_totals.invoice_total_amount, 0) as invoice_total_amount
            ")
            ->get()
            ->map(function ($row) use ($actualGoals, $phase, $priorWinnerUserIds) {
                $isPrizeEligible = ! in_array((int) $row->id, $priorWinnerUserIds, true);
                $goalPrediction = $phase->slug === 'fase-grupos' && $row->group_stage_goal_prediction !== null
                    ? (int) $row->group_stage_goal_prediction
                    : null;
                $goalPredictionDelta = $goalPrediction !== null ? abs($goalPrediction - $actualGoals) : PHP_INT_MAX;
                $rankingTimestamp = $row->registration_completed_at ?? $row->created_at;
                $rankingOrderKey = $row->registration_order_key ?: (string) ($rankingTimestamp ?? $row->created_at);

                return [
                    'user_id' => (int) $row->id,
                    'full_name' => $row->name,
                    'cedula' => $row->cedula,
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'branch_name' => $row->branch_name,
                    'prediction_points' => (float) $row->prediction_points,
                    'invoice_points' => (float) $row->invoice_points,
                    'online_store_points' => (float) $row->online_store_points,
                    'goals' => (float) $row->total_points,
                    'total_points' => (float) $row->total_points,
                    'exact_hits' => (int) $row->exact_hits,
                    'invoice_count' => (int) $row->invoice_count,
                    'invoice_total_amount' => (float) $row->invoice_total_amount,
                    'group_stage_goal_prediction' => $goalPrediction,
                    'actual_goals' => $actualGoals,
                    'goal_prediction_delta' => $goalPredictionDelta,
                    'ranking_timestamp' => $rankingTimestamp,
                    'ranking_order_key' => $rankingOrderKey,
                    'is_prize_eligible' => $isPrizeEligible,
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
            ->map(function (array $row, int $index) {
                $row['position'] = $index + 1;
                $row['football_role'] = $this->footballRole($index);

                return $row;
            });
    }

    public function tieContextForPhase(int $phaseId, int $slots = null, array $excludedUserIds = []): array
    {
        $slots ??= $this->winnerSlots();

        $rows = $this->prizeEligibleLeaderboardForPhase($phaseId, 1000)
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
        return $this->prizeEligibleLeaderboardForPhase($phaseId, 1000)
            ->first(fn (array $row) => ! in_array($row['user_id'], $excludedUserIds, true));
    }

    private function priorWinnerUserIds(int $phaseId): array
    {
        return PromoWinner::query()
            ->whereNotIn('status', ['disqualified'])
            ->where('phase_id', '!=', $phaseId)
            ->pluck('user_id')
            ->map(fn ($userId) => (int) $userId)
            ->all();
    }

    public function leaderboardPhaseIds(TournamentPhase $phase): array
    {
        if (! $this->isKnockoutPhase($phase)) {
            return [$phase->id];
        }

        $phaseIds = TournamentPhase::query()
            ->whereIn('slug', self::KNOCKOUT_PHASE_SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $phaseIds !== [] ? $phaseIds : [$phase->id];
    }

    public function isKnockoutPhase(TournamentPhase $phase): bool
    {
        return in_array($phase->slug, self::KNOCKOUT_PHASE_SLUGS, true);
    }

    public function constrainInvoiceQueryToPhase($query, TournamentPhase $phase)
    {
        [$windowStart, $windowEnd] = $this->invoiceWindowForPhase($phase);
        $groupCutoff = $this->groupStageInvoiceCutoff();

        if ($this->isKnockoutPhase($phase) && $groupCutoff) {
            return $query
                ->where('created_at', '>', $groupCutoff)
                ->where('created_at', '<=', $windowEnd);
        }

        return $query->whereBetween('created_at', [$windowStart, $windowEnd]);
    }

    public function invoiceWindowForPhase(TournamentPhase $phase): array
    {
        $leaderboardPhaseIds = $this->leaderboardPhaseIds($phase);
        $leaderboardPhases = TournamentPhase::query()
            ->whereIn('id', $leaderboardPhaseIds)
            ->get();

        $windowStart = $leaderboardPhases->min('starts_at') ?? $phase->starts_at;
        $windowEnd = $leaderboardPhases->max('ends_at') ?? $phase->ends_at;
        $groupCutoff = $this->groupStageInvoiceCutoff();

        if ($phase->slug === 'fase-grupos' && $groupCutoff) {
            $windowEnd = $groupCutoff;
        }

        if ($this->isKnockoutPhase($phase) && $groupCutoff) {
            $windowStart = $groupCutoff;
        }

        return [$windowStart, $windowEnd];
    }

    public function groupStageInvoiceCutoff(): ?Carbon
    {
        $lastGroupKickoff = TournamentMatch::query()
            ->where('stage_label', 'Group Stage')
            ->whereNotNull('group_label')
            ->whereIn('round_label', ['1', '2', '3'])
            ->max('kickoff_at');

        return $lastGroupKickoff ? Carbon::parse($lastGroupKickoff)->addHours(2) : null;
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
