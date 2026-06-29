<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvoiceGoalSetting;
use App\Models\MatchPrediction;
use App\Models\RegisteredInvoice;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Support\PromotionRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientTournamentController extends Controller
{
    private const KNOCKOUT_PHASE_SLUGS = [
        'dieciseisavos',
        'octavos',
        'cuartos',
        'semifinal',
        'final',
        'semifinal-final',
    ];

    public function __construct(
        private readonly PromotionRankingService $rankingService,
    ) {
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $phases = $this->clientPhasesQuery()->get();
        $activePhase = $phases->first(fn (TournamentPhase $phase) => now()->between($phase->starts_at, $phase->ends_at))
            ?? $phases->first();

        $fullRanking = $activePhase ? $this->rankingService->fullRankedLeaderboard($activePhase->id) : collect();
        $winnerSlots = $activePhase ? $this->rankingService->winnerSlotsForPhase($activePhase->id) : 20;
        $userRankEntry = $fullRanking->first(fn ($row) => $row['user_id'] === $user->id);
        $groupStageContest = $this->contestSummaryForUser($user->id, $this->groupStagePhase(), 'group_stage');
        $knockoutContest = $this->contestSummaryForUser($user->id, $this->knockoutPhase(), 'knockout');

        return response()->json([
            'user' => $user->loadMissing('wallet'),
            'invoice_settings' => InvoiceGoalSetting::query()->first(),
            'active_phase' => $activePhase,
            'phase_goals' => $activePhase ? $this->phaseGoalsForUser($user->id, $activePhase) : 0,
            'general_goals' => $this->generalGoalsForUser($user->id),
            'leaderboard' => $fullRanking->take($winnerSlots)->values()->all(),
            'user_rank' => $userRankEntry ? $userRankEntry['position'] : null,
            'total_participants' => $fullRanking->count(),
            'group_stage_contest' => $groupStageContest,
            'knockout_contest' => $knockoutContest,
        ]);
    }

    public function phases(): JsonResponse
    {
        return response()->json([
            'data' => $this->clientPhasesQuery()->get(),
        ]);
    }

    public function matches(Request $request): JsonResponse
    {
        $phaseId = $request->query('phase_id');
        $allowedPhaseIds = $this->clientPhasesQuery()->pluck('id');

        $matches = TournamentMatch::query()
            ->with(['phase', 'homeTeam', 'awayTeam'])
            ->whereIn('phase_id', $allowedPhaseIds)
            ->withAssignedTeams()
            ->when($phaseId, fn ($query) => $query->where('phase_id', $phaseId))
            ->orderBy('kickoff_at')
            ->get();

        return response()->json([
            'data' => $matches,
        ]);
    }

    public function leaderboard(Request $request, ?int $phaseId = null): JsonResponse
    {
        $phaseId ??= (int) $request->query('phase_id');

        if (! $phaseId || ! $this->clientPhasesQuery()->whereKey($phaseId)->exists()) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $this->rankingService->leaderboardForPhase($phaseId)->all()]);
    }

    private function phaseGoalsForUser(int $userId, TournamentPhase $phase): float
    {
        $phaseIds = $this->rankingService->leaderboardPhaseIds($phase);
        $phaseWindows = TournamentPhase::query()->whereIn('id', $phaseIds)->get();

        $predictionGoals = (float) MatchPrediction::query()
            ->where('user_id', $userId)
            ->whereIn('phase_id', $phaseIds)
            ->sum('points_awarded');

        $invoiceGoals = (float) RegisteredInvoice::query()
            ->where('user_id', $userId)
            ->where('validation_status', 'approved')
            ->when($this->rankingService->isKnockoutPhase($phase), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereBetween('issued_at', [
                $phaseWindows->min('starts_at') ?? $phase->starts_at,
                $phaseWindows->max('ends_at') ?? $phase->ends_at,
            ])
            ->sum('points_awarded');

        return $predictionGoals + $invoiceGoals;
    }

    private function generalGoalsForUser(int $userId): float
    {
        $predictionGoals = (float) MatchPrediction::query()->where('user_id', $userId)->sum('points_awarded');
        $invoiceGoals = (float) RegisteredInvoice::query()
            ->where('user_id', $userId)
            ->where('validation_status', 'approved')
            ->sum('points_awarded');

        return $predictionGoals + $invoiceGoals;
    }

    private function contestSummaryForUser(int $userId, ?TournamentPhase $phase, string $key): ?array
    {
        if (! $phase) {
            return null;
        }

        $fullRanking = $this->rankingService->fullRankedLeaderboard($phase->id);
        $winnerSlots = $this->rankingService->winnerSlotsForPhase($phase->id);
        $userRankEntry = $fullRanking->first(fn ($row) => $row['user_id'] === $userId);

        return [
            'key' => $key,
            'phase' => $phase,
            'user_points' => (float) ($userRankEntry['goals'] ?? 0),
            'user_rank' => $userRankEntry['position'] ?? null,
            'total_participants' => $fullRanking->count(),
            'leaderboard' => $fullRanking->take($winnerSlots)->values()->all(),
        ];
    }

    private function groupStagePhase(): ?TournamentPhase
    {
        return TournamentPhase::query()
            ->where('slug', 'fase-grupos')
            ->first();
    }

    private function knockoutPhase(): ?TournamentPhase
    {
        return TournamentPhase::query()
            ->where('slug', 'final')
            ->first()
            ?? TournamentPhase::query()
                ->whereIn('slug', self::KNOCKOUT_PHASE_SLUGS)
                ->orderByDesc('stage_order')
                ->first();
    }

    private function clientPhasesQuery()
    {
        return TournamentPhase::query()
            ->where('is_active', true)
            ->where('ends_at', '>=', now())
            ->where(function ($query): void {
                $query
                    ->where(function ($groupStageQuery): void {
                        $groupStageQuery
                            ->where('slug', 'fase-grupos')
                            ->where('starts_at', '<=', now());
                    })
                    ->orWhere('slug', '!=', 'fase-grupos');
            })
            ->whereHas('matches', fn ($query) => $query->withAssignedTeams())
            ->orderBy('stage_order');
    }
}
