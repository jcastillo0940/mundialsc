<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlay extends Model
{
    protected $fillable = [
        'user_id',
        'campaign_id',
        'game_type',
        'client_choice',
        'result_type',
        'prize_id',
        'window_id',
        'shots_spent',
        'played_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }
}
