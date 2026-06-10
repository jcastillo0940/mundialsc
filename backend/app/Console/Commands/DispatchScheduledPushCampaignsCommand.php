<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\PushCampaignDispatcher;
use Illuminate\Console\Command;

class DispatchScheduledPushCampaignsCommand extends Command
{
    protected $signature = 'push-campaigns:dispatch-scheduled';

    protected $description = 'Dispatch scheduled push campaigns whose send time has arrived.';

    public function handle(PushCampaignDispatcher $dispatcher): int
    {
        $campaigns = Campaign::query()
            ->where('push_status', 'scheduled')
            ->whereNotNull('push_send_at')
            ->where('push_send_at', '<=', now())
            ->orderBy('push_send_at')
            ->get();

        $sent = 0;

        foreach ($campaigns as $campaign) {
            $dispatcher->dispatch($campaign);
            $sent++;
        }

        $this->info("{$sent} campaign(s) dispatched.");

        return self::SUCCESS;
    }
}
