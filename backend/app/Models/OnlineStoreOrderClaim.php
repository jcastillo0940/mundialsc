<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineStoreOrderClaim extends Model
{
    protected $fillable = [
        'user_id',
        'online_store_order_id',
        'increment_id',
        'submitted_email',
        'magento_order_id',
        'customer_email',
        'grand_total',
        'currency',
        'magento_status',
        'ordered_at',
        'status',
        'source',
        'points_awarded',
        'submitted_at',
        'source_reported_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'created_by_user_id',
        'review_notes',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'grand_total' => 'decimal:2',
            'ordered_at' => 'datetime',
            'submitted_at' => 'datetime',
            'source_reported_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlineStoreOrder::class, 'online_store_order_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
