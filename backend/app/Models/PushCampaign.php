<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushCampaign extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image_url',
        'button_text',
        'button_url',
        'audience_type',
        'target_user_id',
        'send_at',
        'status',
        'sent_count',
        'failed_count',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
