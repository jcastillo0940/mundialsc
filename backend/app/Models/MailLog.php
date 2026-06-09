<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailLog extends Model
{
    protected $fillable = [
        'channel',
        'event_type',
        'recipient_email',
        'subject',
        'status',
        'subscriber_id',
        'user_id',
        'meta',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
