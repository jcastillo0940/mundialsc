<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'invoice_min_amount_for_shot',
        'amount_per_point',
        'points_per_block',
        'daily_max_points',
        'daily_max_invoices',
        'coupon_ttl_hours',
        'games_enabled',
        'major_prizes_enabled',
        'invoice_scan_enabled',
        'redemption_enabled',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'games_enabled' => 'boolean',
            'major_prizes_enabled' => 'boolean',
            'invoice_scan_enabled' => 'boolean',
            'redemption_enabled' => 'boolean',
            'invoice_min_amount_for_shot' => 'decimal:2',
            'amount_per_point' => 'decimal:2',
        ];
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(Prize::class);
    }

    public function windows(): HasMany
    {
        return $this->hasMany(InstantWinWindow::class);
    }
}
