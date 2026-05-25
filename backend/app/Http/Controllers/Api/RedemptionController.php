<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Prize;
use App\Models\PrizeInventoryMovement;
use App\Models\Wallet;
use App\Models\WalletMovement;
use App\Support\Audit;
use App\Support\CampaignManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RedemptionController extends Controller
{
    public function __construct(
        private readonly CampaignManager $campaignManager,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prize_id' => ['required', 'integer', 'exists:prizes,id'],
        ]);

        $user = $request->user();
        $campaign = $this->campaignManager->activeOrFail();

        if (! $campaign->redemption_enabled) {
            throw ValidationException::withMessages([
                'campaign' => 'Los canjes están deshabilitados.',
            ]);
        }

        $coupon = DB::transaction(function () use ($user, $campaign, $data): Coupon {
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $prize = Prize::query()->whereKey($data['prize_id'])->lockForUpdate()->firstOrFail();

            if (! $prize->is_active || $prize->redemption_type !== 'direct') {
                throw ValidationException::withMessages([
                    'prize_id' => 'El premio seleccionado no está disponible para canje directo.',
                ]);
            }

            if ($prize->campaign_id !== $campaign->id) {
                throw ValidationException::withMessages([
                    'prize_id' => 'El premio no pertenece a la campaña activa.',
                ]);
            }

            if ($prize->available_stock <= 0) {
                throw ValidationException::withMessages([
                    'stock' => 'El premio está agotado.',
                ]);
            }

            if ($wallet->goals_balance < (int) $prize->points_cost) {
                throw ValidationException::withMessages([
                    'wallet' => 'No tienes goles suficientes para este canje.',
                ]);
            }

            if ($prize->category === 'major') {
                $alreadyWonMajorPrize = Coupon::query()
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['generated', 'delivered'])
                    ->whereHas('prize', fn ($query) => $query->where('category', 'major'))
                    ->exists();

                if ($alreadyWonMajorPrize) {
                    throw ValidationException::withMessages([
                        'prize' => 'Ya alcanzaste el límite de un premio mayor durante la campaña.',
                    ]);
                }
            }

            $wallet->decrement('goals_balance', (int) $prize->points_cost);
            $prize->increment('reserved_stock');

            $coupon = Coupon::query()->create([
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'prize_id' => $prize->id,
                'source_type' => 'direct_redemption',
                'code' => (string) Str::uuid(),
                'qr_payload' => json_encode([
                    'coupon_code' => null,
                    'prize_slug' => $prize->slug,
                    'user_id' => $user->id,
                    'campaign_id' => $campaign->id,
                ], JSON_THROW_ON_ERROR),
                'status' => 'generated',
                'expires_at' => now()->addHours($campaign->coupon_ttl_hours),
            ]);

            PrizeInventoryMovement::query()->create([
                'prize_id' => $prize->id,
                'movement_type' => 'reserve',
                'quantity' => 1,
                'related_coupon_id' => $coupon->id,
                'notes' => 'Reserva por canje directo.',
                'created_by_user_id' => $user->id,
                'created_at' => now(),
            ]);

            $coupon->update([
                'qr_payload' => json_encode([
                    'coupon_code' => $coupon->code,
                    'prize_slug' => $prize->slug,
                    'user_id' => $user->id,
                    'campaign_id' => $campaign->id,
                ], JSON_THROW_ON_ERROR),
            ]);

            WalletMovement::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'type' => 'redeem_points_debit',
                'resource_type' => 'coupon',
                'resource_id' => $coupon->id,
                'goals_delta' => -1 * (int) $prize->points_cost,
                'shots_delta' => 0,
                'notes' => 'Canje directo en tienda de fidelidad.',
                'meta' => ['prize_id' => $prize->id],
                'created_at' => now(),
            ]);

            return $coupon->load('prize');
        });

        Audit::log('coupon.created', 'coupon', $coupon->id, $user, $request, [
            'source_type' => 'direct_redemption',
            'prize_id' => $coupon->prize_id,
        ]);

        return response()->json([
            'message' => 'Canje realizado. Tu cupón fue generado.',
            'coupon' => $coupon,
            'wallet' => $user->fresh('wallet')->wallet,
        ], 201);
    }
}
