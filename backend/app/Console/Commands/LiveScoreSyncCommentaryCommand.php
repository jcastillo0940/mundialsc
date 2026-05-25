<?php

namespace App\Console\Commands;

use App\Support\LiveScoreSyncService;
use Illuminate\Console\Command;

class LiveScoreSyncCommentaryCommand extends Command
{
    protected $signature = 'livescore:sync-commentary';

    protected $description = 'Sincroniza commentary de partidos con external_match_id.';

    public function handle(LiveScoreSyncService $service): int
    {
        $run = $service->syncCommentary();
        $this->info('Estado: '.$run->status);
        if ($run->error_message) {
            $this->error($run->error_message);
        }

        return $run->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
