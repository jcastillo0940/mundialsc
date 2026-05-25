<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveScoreSyncRun extends Model
{
    protected $table = 'live_score_sync_runs';

    protected $fillable = [
        'sync_type',
        'status',
        'requested_by_user_id',
        'records_created',
        'records_updated',
        'records_skipped',
        'context',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
