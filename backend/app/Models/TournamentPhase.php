<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentPhase extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'stage_order',
        'starts_at',
        'ends_at',
        'exact_score_points',
        'outcome_points',
        'reset_phase_table',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reset_phase_table' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'phase_id');
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(PhasePrize::class, 'phase_id');
    }
}
