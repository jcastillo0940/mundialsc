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
        if (! $service->shouldRunNow('fixtures', $service->fixturesSyncIntervalHours() * 3600)) {
            $this->info('Saltado: todavia no corresponde al siguiente intervalo configurado.');
            return self::SUCCESS;
        }

        $run = $service->syncFixtures();
        $this->info('Estado: '.$run->status);
        if ($run->error_message) {
            $this->error($run->error_message);
        }

        return $run->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
