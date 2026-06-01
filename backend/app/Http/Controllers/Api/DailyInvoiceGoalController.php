<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegisteredInvoice;
use App\Support\ContestInvoiceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyInvoiceGoalController extends Controller
{
    public function __construct(
        private readonly ContestInvoiceRegistrationService $registrationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => RegisteredInvoice::query()
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(20)
                ->get(),
            'totals' => [
                'goals' => (float) RegisteredInvoice::query()
                    ->where('user_id', $request->user()->id)
                    ->where('validation_status', 'approved')
                    ->sum('points_awarded'),
            ],
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
