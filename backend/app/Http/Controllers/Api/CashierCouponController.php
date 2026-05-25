<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Prize;
use App\Models\PrizeInventoryMovement;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashierCouponController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:36'],
        ]);

        $coupon = Coupon::query()
            ->with('prize')
            ->where('code', $data['code'])
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'code' => 'El cupón no existe.',
            ]);
        }

        return response()->json([
            'coupon' => $coupon,
            'can_deliver' => $coupon->status === 'generated' && $coupon->expires_at->isFuture(),
        ]);
    }

    public function deliver(Request $request, string $code): JsonResponse
    {
        $cashier = $request->user();

        $coupon = DB::transaction(function () use ($cashier, $code): Coupon {
            $coupon = Coupon::query()
                ->with('prize')
                ->where('code', $code)
                ->lockForUpdate()
                ->firstOrFail();

            if ($coupon->status !== 'generated') {
                throw ValidationException::withMessages([
                    'coupon' => 'Este cupón ya no está disponible para entrega.',
                ]);
            }

            if ($coupon->expires_at->isPast()) {
                $coupon->update(['status' => 'expired']);

                throw ValidationException::withMessages([
                    'coupon' => 'El cupón ya expiró.',
                ]);
            }

            $prize = Prize::query()->whereKey($coupon->prize_id)->lockForUpdate()->firstOrFail();

            if ($prize->reserved_stock > 0) {
                $prize->decrement('reserved_stock');
            }

            $prize->increment('delivered_stock');

            PrizeInventoryMovement::query()->create([
                'prize_id' => $prize->id,
                'movement_type' => 'deliver',
                'quantity' => 1,
                'related_coupon_id' => $coupon->id,
                'notes' => 'Entrega física confirmada por caja.',
                'created_by_user_id' => $cashier->id,
                'created_at' => now(),
            ]);

            $coupon->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'delivered_by_user_id' => $cashier->id,
                'delivered_branch_id' => $cashier->branch_id,
            ]);

            return $coupon->fresh('prize');
        });

        Audit::log('coupon.delivered', 'coupon', $coupon->id, $cashier, $request, [
            'delivered_branch_id' => $cashier->branch_id,
        ]);

        return response()->json([
            'message' => 'Cupón entregado correctamente.',
            'coupon' => $coupon,
        ]);
    }
}
