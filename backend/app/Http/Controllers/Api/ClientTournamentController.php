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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientTournamentController extends Controller
{
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

        return response()->json([
            'user' => $user->loadMissing('wallet'),
            'invoice_settings' => InvoiceGoalSetting::query()->first(),
            'active_phase' => $activePhase,
            'phase_goals' => $activePhase ? $this->phaseGoalsForUser($user->id, $activePhase->id) : 0,
            'general_goals' => $this->generalGoalsForUser($user->id),
            'leaderboard' => $activePhase ? $this->rankingService->leaderboardForPhase($activePhase->id)->all() : [],
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

    private function phaseGoalsForUser(int $userId, int $phaseId): float
    {
        $predictionGoals = (float) MatchPrediction::query()->where('user_id', $userId)->where('phase_id', $phaseId)->sum('points_awarded');
        $invoiceGoals = (float) RegisteredInvoice::query()
            ->where('user_id', $userId)
            ->where('validation_status', 'approved')
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

    private function clientPhasesQuery()
    {
        return TournamentPhase::query()
            ->where('is_active', true)
            ->orderBy('stage_order');
    }
}
