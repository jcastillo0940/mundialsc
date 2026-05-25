<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    protected $fillable = [
        'user_id',
        'campaign_id',
        'prize_id',
        'source_type',
        'code',
        'qr_payload',
        'status',
        'expires_at',
        'delivered_at',
        'delivered_by_user_id',
        'delivered_branch_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }
}
