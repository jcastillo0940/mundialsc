<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstantWinWindow extends Model
{
    protected $fillable = [
        'campaign_id',
        'prize_id',
        'opens_at',
        'closes_at',
        'is_consumed',
        'consumed_by_user_id',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'consumed_at' => 'datetime',
            'is_consumed' => 'boolean',
        ];
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }
}
