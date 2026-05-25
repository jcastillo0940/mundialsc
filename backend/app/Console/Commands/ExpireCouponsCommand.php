<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\Prize;
use App\Models\PrizeInventoryMovement;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireCouponsCommand extends Command
{
    protected $signature = 'supercarnes:expire-coupons';

    protected $description = 'Expira cupones vencidos y libera el stock reservado.';

    public function handle(): int
    {
        $expiredCount = 0;

        Coupon::query()
            ->where('status', 'generated')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($coupons) use (&$expiredCount): void {
                foreach ($coupons as $coupon) {
                    DB::transaction(function () use ($coupon, &$expiredCount): void {
                        $lockedCoupon = Coupon::query()
                            ->whereKey($coupon->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $lockedCoupon || $lockedCoupon->status !== 'generated' || $lockedCoupon->expires_at->isFuture()) {
                            return;
                        }

                        $prize = Prize::query()->whereKey($lockedCoupon->prize_id)->lockForUpdate()->first();

                        if ($prize && $prize->reserved_stock > 0) {
                            $prize->decrement('reserved_stock');

                            PrizeInventoryMovement::query()->create([
                                'prize_id' => $prize->id,
                                'movement_type' => 'release',
                                'quantity' => 1,
                                'related_coupon_id' => $lockedCoupon->id,
                                'notes' => 'Liberación automática por expiración de cupón.',
                                'created_at' => now(),
                            ]);
                        }

                        $lockedCoupon->update([
                            'status' => 'expired',
                        ]);

                        Audit::log('coupon.expired', 'coupon', $lockedCoupon->id, null, null, [
                            'prize_id' => $lockedCoupon->prize_id,
                        ]);

                        $expiredCount++;
                    });
                }
            });

        $this->info("Cupones expirados: {$expiredCount}");

        return self::SUCCESS;
    }
}
