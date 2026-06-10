<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\FcmToken;

class PushCampaignDispatcher
{
    public function __construct(private readonly FirebaseMessagingService $firebaseMessaging)
    {
    }

    public function dispatch(Campaign $campaign): array
    {
        $campaign->forceFill(['push_status' => 'sending', 'push_error_message' => null])->save();

        $query = FcmToken::query()->where('is_enabled', true);

        if ($campaign->push_audience_type === 'user' && $campaign->push_target_user_id) {
            $query->where('user_id', $campaign->push_target_user_id);
        } elseif ($campaign->push_audience_type === 'branch' && $campaign->push_target_branch_id) {
            $query->whereHas('user', fn ($branchQuery) => $branchQuery->where('branch_id', $campaign->push_target_branch_id));
        } elseif ($campaign->push_audience_type === 'active' || $campaign->push_only_active_users) {
            $query->whereHas('user', fn ($activeQuery) => $activeQuery->where('is_active', true));
        }

        $result = $this->firebaseMessaging->sendCampaign($campaign->fresh(), $query->get());

        $campaign->forceFill([
            'push_status' => 'sent',
            'push_sent_count' => $result['sent'],
            'push_failed_count' => $result['failed'],
            'push_sent_at' => now(),
        ])->save();

        return $result;
    }
}
