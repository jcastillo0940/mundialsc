<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisteredInvoice extends Model
{
    protected $table = 'registered_invoices';

    protected $fillable = [
        'user_id',
        'campaign_id',
        'branch_id',
        'cufe',
        'qr_raw_text',
        'invoice_number',
        'fiscal_document_type',
        'issued_at',
        'purchase_amount',
        'points_awarded',
        'shots_awarded',
        'daily_points_capped',
        'daily_invoice_limit_hit',
        'status',
        'validation_status',
        'validation_notes',
        'dgi_checked_at',
        'dgi_response_payload',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'purchase_amount' => 'decimal:2',
            'daily_points_capped' => 'boolean',
            'daily_invoice_limit_hit' => 'boolean',
            'dgi_checked_at' => 'datetime',
            'dgi_response_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
