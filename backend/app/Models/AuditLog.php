<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'actor_role',
        'event_type',
        'entity_type',
        'entity_id',
        'ip_address',
        'user_agent',
        'payload',
        'previous_hash',
        'entry_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
