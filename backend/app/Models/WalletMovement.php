<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'campaign_id',
        'type',
        'resource_type',
        'resource_id',
        'goals_delta',
        'shots_delta',
        'notes',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
