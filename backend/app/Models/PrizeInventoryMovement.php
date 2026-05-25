<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeInventoryMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'prize_id',
        'movement_type',
        'quantity',
        'related_coupon_id',
        'notes',
        'created_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
