<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveScoreCommentaryEvent extends Model
{
    protected $table = 'live_score_commentary_events';

    protected $fillable = [
        'tournament_match_id',
        'external_match_id',
        'external_event_id',
        'event_type',
        'minute',
        'second_label',
        'match_second',
        'comment_text',
        'text_label',
        'pos_x',
        'pos_y',
        'side',
        'external_team_id',
        'team_name',
        'external_player_id',
        'player_name',
        'external_player_2_id',
        'player_2_name',
        'provider_created_at',
        'provider_updated_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'pos_x' => 'decimal:2',
            'pos_y' => 'decimal:2',
            'provider_created_at' => 'datetime',
            'provider_updated_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function tournamentMatch(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'tournament_match_id');
    }
}
