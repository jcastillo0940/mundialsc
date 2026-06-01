<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\GamePlay;
use App\Models\InstantWinWindow;
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

class GameController extends Controller
{
    public function __construct(
        private readonly CampaignManager $campaignManager,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game_type' => ['required', 'in:mete_gol,ruleta'],
            'client_choice' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $campaign = $this->campaignManager->activeOrFail();

        if (! $campaign->games_enabled) {
            throw ValidationException::withMessages([
                'campaign' => 'La arena de juegos está deshabilitada.',
            ]);
        }

        $result = DB::transaction(function () use ($user, $campaign, $data) {
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->shots_balance < 1) {
                throw ValidationException::withMessages([
                    'wallet' => 'No tienes tiros disponibles.',
                ]);
            }

            $wallet->decrement('shots_balance', 1);

            WalletMovement::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'type' => 'game_shot_debit',
                'resource_type' => 'game_play',
                'resource_id' => null,
                'goals_delta' => 0,
                'shots_delta' => -1,
                'notes' => 'Consumo de tiro en arena de juegos.',
                'meta' => [
                    'source' => 'game',
                    'rule_code' => 'game_shot_debit',
                    'rule_label' => 'Consumo de tiro en juego',
                    'game_type' => $data['game_type'],
                    'rule_snapshot' => [
                        'shots_spent' => 1,
                        'major_prizes_enabled' => (bool) $campaign->major_prizes_enabled,
                    ],
                ],
                'created_at' => now(),
            ]);

            $window = InstantWinWindow::query()
                ->where('campaign_id', $campaign->id)
                ->where('opens_at', '<=', now())
                ->where('closes_at', '>=', now())
                ->where('is_consumed', false)
                ->lockForUpdate()
                ->first();

            $prize = null;
            $coupon = null;
            $resultType = 'no_win';

            if ($window && $campaign->major_prizes_enabled) {
                $prize = Prize::query()->whereKey($window->prize_id)->lockForUpdate()->first();

                $alreadyWonMajorPrize = Coupon::query()
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['generated', 'delivered'])
                    ->whereHas('prize', fn ($query) => $query->where('category', 'major'))
                    ->exists();

                if ($prize && $prize->available_stock > 0 && ! $alreadyWonMajorPrize) {
                    $window->update([
                        'is_consumed' => true,
                        'consumed_by_user_id' => $user->id,
                        'consumed_at' => now(),
                    ]);

                    $prize->increment('reserved_stock');
                    $coupon = $this->createCoupon($user->id, $campaign->id, $prize, 'instant_win', $campaign->coupon_ttl_hours);
                    $resultType = 'major_prize';
                }
            }

            if (! $coupon) {
                $prize = Prize::query()
                    ->where('campaign_id', $campaign->id)
                    ->where('redemption_type', 'instant_win')
                    ->where('category', 'consolation')
                    ->where('is_active', true)
                    ->inRandomOrder()
                    ->first();

                if ($prize && $prize->available_stock > 0 && random_int(1, 100) <= 35) {
                    $prize = Prize::query()->whereKey($prize->id)->lockForUpdate()->first();

                    if ($prize && $prize->available_stock > 0) {
                        $prize->increment('reserved_stock');
                        $coupon = $this->createCoupon($user->id, $campaign->id, $prize, 'instant_win', $campaign->coupon_ttl_hours);
                        $resultType = 'consolation_prize';
                    }
                }
            }

            $play = GamePlay::query()->create([
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'game_type' => $data['game_type'],
                'client_choice' => $data['client_choice'] ?? null,
                'result_type' => $resultType,
                'prize_id' => $coupon?->prize_id,
                'window_id' => $window?->id,
                'shots_spent' => 1,
                'played_at' => now(),
                'meta' => ['coupon_code' => $coupon?->code],
            ]);

            return [
                'play' => $play,
                'coupon' => $coupon?->load('prize'),
                'wallet' => $user->fresh('wallet')->wallet,
                'result' => [
                    'type' => $resultType,
                    'label' => match ($resultType) {
                        'major_prize' => 'Televisor',
                        'consolation_prize' => $coupon?->prize?->name ?? 'Premio de consolación',
                        default => 'Siga participando',
                    },
                ],
            ];
        });

        Audit::log('game.played', 'game_play', $result['play']->id, $user, $request, [
            'result_type' => $result['play']->result_type,
            'game_type' => $result['play']->game_type,
        ]);

        return response()->json($result, 201);
    }

    private function createCoupon(int $userId, int $campaignId, Prize $prize, string $sourceType, int $ttlHours): Coupon
    {
        $coupon = Coupon::query()->create([
            'user_id' => $userId,
            'campaign_id' => $campaignId,
            'prize_id' => $prize->id,
            'source_type' => $sourceType,
            'code' => (string) Str::uuid(),
            'qr_payload' => '',
            'status' => 'generated',
            'expires_at' => now()->addHours($ttlHours),
        ]);

        $coupon->update([
            'qr_payload' => json_encode([
                'coupon_code' => $coupon->code,
                'prize_slug' => $prize->slug,
                'user_id' => $userId,
                'campaign_id' => $campaignId,
            ], JSON_THROW_ON_ERROR),
        ]);

        PrizeInventoryMovement::query()->create([
            'prize_id' => $prize->id,
            'movement_type' => 'reserve',
            'quantity' => 1,
            'related_coupon_id' => $coupon->id,
            'notes' => 'Reserva por instant win.',
            'created_by_user_id' => $userId,
            'created_at' => now(),
        ]);

        return $coupon;
    }
}
