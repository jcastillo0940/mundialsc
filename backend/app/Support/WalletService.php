<?php

namespace App\Support;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function ensureWallet(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'goals_balance'         => 0,
                'shots_balance'         => 0,
                'lifetime_goals_earned' => 0,
                'lifetime_shots_earned' => 0,
            ],
        );
    }

    public function creditGoals(
        User $user,
        int $amount,
        string $type,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?int $campaignId = null,
        ?string $notes = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $amount, $type, $resourceType, $resourceId, $campaignId, $notes): void {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first() ?? $this->ensureWallet($user);

            $wallet->increment('goals_balance', $amount);
            $wallet->increment('lifetime_goals_earned', $amount);

            WalletMovement::query()->create([
                'wallet_id'     => $wallet->id,
                'user_id'       => $user->id,
                'campaign_id'   => $campaignId,
                'type'          => $type,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'goals_delta'   => $amount,
                'shots_delta'   => 0,
                'notes'         => $notes,
                'created_at'    => now(),
            ]);
        });
    }
}
