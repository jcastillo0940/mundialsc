<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveScoreSetting extends Model
{
    protected $table = 'live_score_settings';

    protected $fillable = [
        'provider_name',
        'is_enabled',
        'competition_id',
        'competition_ids',
        'season',
        'lang',
        'sync_from_date',
        'sync_to_date',
        'auto_sync_commentary',
        'fixtures_sync_interval_hours',
        'live_sync_interval_minutes',
        'commentary_sync_interval_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'auto_sync_commentary' => 'boolean',
            'sync_from_date' => 'date',
            'sync_to_date' => 'date',
            'fixtures_sync_interval_hours' => 'integer',
            'live_sync_interval_minutes' => 'integer',
            'commentary_sync_interval_minutes' => 'integer',
        ];
    }
}
