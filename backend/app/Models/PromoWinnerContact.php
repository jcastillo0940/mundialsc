<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoWinnerContact extends Model
{
    protected $fillable = [
        'promo_winner_id',
        'contact_type',
        'contact_status',
        'contacted_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
        ];
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(PromoWinner::class, 'promo_winner_id');
    }
}
