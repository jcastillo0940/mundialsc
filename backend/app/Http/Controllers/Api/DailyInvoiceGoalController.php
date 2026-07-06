<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegisteredInvoice;
use App\Support\ContestInvoiceRegistrationService;
use App\Support\PromotionRankingService;
use App\Support\TournamentPhaseResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyInvoiceGoalController extends Controller
{
    public function __construct(
        private readonly ContestInvoiceRegistrationService $registrationService,
        private readonly TournamentPhaseResolver $phaseResolver,
        private readonly PromotionRankingService $rankingService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $activePhase = $this->phaseResolver->currentPhase();
        $invoiceTotalsQuery = RegisteredInvoice::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('validation_status', RegisteredInvoice::APPROVED_VALIDATION_STATUSES);

        $phaseGoalsQuery = clone $invoiceTotalsQuery;
        $phaseAmountQuery = clone $invoiceTotalsQuery;

        if ($activePhase) {
            $this->rankingService->constrainInvoiceQueryToPhase($phaseGoalsQuery, $activePhase);
            $this->rankingService->constrainInvoiceQueryToPhase($phaseAmountQuery, $activePhase);
        }

        return response()->json([
            'data' => RegisteredInvoice::query()
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(20)
                ->get(),
            'totals' => [
                'goals' => (float) (clone $invoiceTotalsQuery)->sum('points_awarded'),
                'amount' => (float) (clone $invoiceTotalsQuery)->sum('purchase_amount'),
                'phase_goals' => $activePhase
                    ? (float) $phaseGoalsQuery->sum('points_awarded')
                    : 0,
                'phase_amount' => $activePhase
                    ? (float) $phaseAmountQuery->sum('purchase_amount')
                    : 0,
            ],
            'active_phase' => $activePhase,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_raw_text' => ['required', 'string', 'max:2048'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $result = $this->registrationService->register($request->user(), $data, $request);

        return response()->json([
            'message' => $result['message'],
            'entry' => $result['invoice'],
        ], 201);
    }

    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_raw_text' => ['required', 'string', 'max:2048'],
        ]);

        $result = $this->registrationService->resolveInvoiceData($data['qr_raw_text']);

        return response()->json([
            'data' => $result,
        ]);
    }
}
