<?php

namespace App\Console\Commands;

use App\Support\LiveScoreSyncService;
use Illuminate\Console\Command;

class LiveScoreSyncLiveCommand extends Command
{
    protected $signature = 'livescore:sync-live';

    protected $description = 'Sincroniza partidos en vivo desde Live Score API.';

    public function handle(LiveScoreSyncService $service): int
    {
        $run = $service->syncLive();
        $this->info('Estado: '.$run->status);
        if ($run->error_message) {
            $this->error($run->error_message);
        }

        return $run->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
