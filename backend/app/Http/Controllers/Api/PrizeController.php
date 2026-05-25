<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrizeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $wonMajorPrize = $user->coupons()
            ->whereHas('prize', fn ($query) => $query->where('category', 'major'))
            ->exists();

        $query = Prize::query()
            ->with('campaign')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name');

        if ($wonMajorPrize) {
            $query->where('category', '!=', 'major');
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }
}
