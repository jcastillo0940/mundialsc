<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoWinner extends Model
{
    protected $fillable = [
        'phase_id',
        'user_id',
        'leaderboard_position',
        'total_points',
        'exact_hits',
        'invoice_count',
        'invoice_total_amount',
        'goal_prediction_delta',
        'ranking_timestamp',
        'selection_reason',
        'status',
        'replacement_for_winner_id',
        'notes',
        'selected_at',
        'last_contact_at',
        'responded_at',
        'disqualified_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_points' => 'decimal:2',
            'invoice_total_amount' => 'decimal:2',
            'goal_prediction_delta' => 'integer',
            'ranking_timestamp' => 'datetime',
            'selected_at' => 'datetime',
            'last_contact_at' => 'datetime',
            'responded_at' => 'datetime',
            'disqualified_at' => 'datetime',
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TournamentPhase::class, 'phase_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PromoWinnerContact::class, 'promo_winner_id');
    }

    public function replacementFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replacement_for_winner_id');
    }
}
