<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\RegisteredInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('wallet');

        return response()->json([
            'wallet' => $user->wallet,
            'stats' => [
                'registered_invoices' => RegisteredInvoice::query()->where('user_id', $user->id)->count(),
                'active_coupons' => Coupon::query()->where('user_id', $user->id)->where('status', 'generated')->count(),
                'delivered_coupons' => Coupon::query()->where('user_id', $user->id)->where('status', 'delivered')->count(),
            ],
            'recent_invoices' => RegisteredInvoice::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(5)
                ->get(),
            'recent_coupons' => Coupon::query()
                ->with('prize')
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }

    public function wallet(Request $request): JsonResponse
    {
        return response()->json([
            'wallet' => $request->user()->loadMissing('wallet')->wallet,
            'movements' => $request->user()->wallet?->movements()->latest('id')->limit(20)->get() ?? [],
        ]);
    }
}
