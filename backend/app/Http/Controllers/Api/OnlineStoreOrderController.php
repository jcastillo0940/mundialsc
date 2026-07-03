<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\OnlineStoreOrderBonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineStoreOrderController extends Controller
{
    public function verify(Request $request, OnlineStoreOrderBonusService $bonusService): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:80'],
        ]);

        return response()->json($bonusService->verifyForUser(
            $request->user(),
            $validated['order_number'] ?? null,
        ));
    }
}
