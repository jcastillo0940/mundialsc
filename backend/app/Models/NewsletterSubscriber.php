<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class NewsletterSubscriber extends Model
{
    use Notifiable;

    protected $fillable = [
        'email',
        'subscribed_at',
        'unsubscribed_at',
        'confirmed_at',
        'confirmation_token_hash',
        'confirmation_sent_at',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
        ];
    }
}
