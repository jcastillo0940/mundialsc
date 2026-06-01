<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $appends = ['flag_url'];

    protected $fillable = [
        'external_team_id',
        'external_country_id',
        'name',
        'code',
        'ranking_fifa',
        'frozen_ranking_fifa',
        'ranking_frozen_at',
        'group_label',
        'flag_emoji',
        'provider_logo_url',
        'provider_flag_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ranking_fifa' => 'integer',
            'frozen_ranking_fifa' => 'integer',
            'ranking_frozen_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected function getFlagUrlAttribute(): ?string
    {
        return $this->external_team_id
            ? route('api.client.teams.flag', ['team' => $this->id])
            : null;
    }
}
