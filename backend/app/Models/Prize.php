<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prize extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'slug',
        'description',
        'category',
        'redemption_type',
        'points_cost',
        'shots_cost',
        'total_stock',
        'reserved_stock',
        'delivered_stock',
        'image_url',
        'is_active',
    ];

    protected $appends = ['available_stock'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function getAvailableStockAttribute(): int
    {
        return (int) ($this->total_stock - $this->reserved_stock - $this->delivered_stock);
    }
}
