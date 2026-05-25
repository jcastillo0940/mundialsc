<?php

namespace App\Console\Commands;

use App\Support\LiveScoreSyncService;
use Illuminate\Console\Command;

class LiveScoreSyncFixturesCommand extends Command
{
    protected $signature = 'livescore:sync-fixtures';

    protected $description = 'Sincroniza fixtures desde Live Score API.';

    public function handle(LiveScoreSyncService $service): int
    {
        $run = $service->syncFixtures();
        $this->info('Estado: '.$run->status);
        if ($run->error_message) {
            $this->error($run->error_message);
        }

        return $run->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
