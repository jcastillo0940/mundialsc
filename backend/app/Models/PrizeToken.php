<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrizeToken extends Model
{
    protected $fillable = [
        'phase_id',
        'phase_prize_id',
        'token_code',
        'prize_title',
        'prize_type',
        'status',
        'current_promo_winner_id',
        'assigned_user_id',
        'assigned_at',
        'delivered_at',
        'reassigned_from_promo_winner_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TournamentPhase::class, 'phase_id');
    }

    public function phasePrize(): BelongsTo
    {
        return $this->belongsTo(PhasePrize::class, 'phase_prize_id');
    }

    public function currentWinner(): BelongsTo
    {
        return $this->belongsTo(PromoWinner::class, 'current_promo_winner_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
