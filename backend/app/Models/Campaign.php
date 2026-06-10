<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'push_title',
        'push_description',
        'push_image_url',
        'push_button_text',
        'push_button_url',
        'push_audience_type',
        'push_target_user_id',
        'push_target_branch_id',
        'push_only_active_users',
        'push_send_at',
        'push_status',
        'push_sent_count',
        'push_failed_count',
        'push_error_message',
        'push_sent_at',
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
            'push_send_at' => 'datetime',
            'push_sent_at' => 'datetime',
            'push_only_active_users' => 'boolean',
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

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'push_target_user_id');
    }

    public function targetBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'push_target_branch_id');
    }
}
