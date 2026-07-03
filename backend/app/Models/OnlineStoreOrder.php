<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineStoreOrder extends Model
{
    protected $fillable = [
        'user_id',
        'magento_order_id',
        'increment_id',
        'customer_email',
        'grand_total',
        'currency',
        'status',
        'ordered_at',
        'points_awarded',
        'credited_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'grand_total' => 'decimal:2',
            'ordered_at' => 'datetime',
            'credited_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
