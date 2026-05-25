<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Coupon::query()
                ->with('prize')
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $coupon = Coupon::query()
            ->with('prize')
            ->where('code', $code)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'coupon' => $coupon,
        ]);
    }
}
