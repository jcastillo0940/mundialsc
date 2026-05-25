<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhasePrize extends Model
{
    protected $table = 'phase_prizes';

    protected $fillable = [
        'phase_id',
        'ranking_from',
        'ranking_to',
        'football_role',
        'prize_title',
        'prize_type',
        'stock',
    ];

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TournamentPhase::class, 'phase_id');
    }
}
