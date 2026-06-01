<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class TournamentMatch extends Model
{
    protected $table = 'tournament_matches';

    protected $fillable = [
        'external_fixture_id',
        'external_match_id',
        'phase_id',
        'match_number',
        'external_group_id',
        'group_label',
        'round_label',
        'stage_label',
        'venue_name',
        'home_team_id',
        'away_team_id',
        'favorite_side',
        'kickoff_at',
        'home_score',
        'away_score',
        'status',
        'provider',
        'provider_status',
        'provider_competition_name',
        'kickoff_timezone',
        'live_score_last_synced_at',
        'commentary_last_synced_at',
        'raw_provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'live_score_last_synced_at' => 'datetime',
            'commentary_last_synced_at' => 'datetime',
            'raw_provider_payload' => 'array',
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TournamentPhase::class, 'phase_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(MatchPrediction::class, 'match_id');
    }

    public function resultApprovals(): HasMany
    {
        return $this->hasMany(MatchResultApproval::class, 'tournament_match_id');
    }

    public function scopeWithAssignedTeams(Builder $query): Builder
    {
        return $query
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->whereHas('homeTeam', fn (Builder $teamQuery) => $teamQuery->resolvedTournamentTeam())
            ->whereHas('awayTeam', fn (Builder $teamQuery) => $teamQuery->resolvedTournamentTeam());
    }
}
