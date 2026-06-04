<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'issuer_ruc',
        'issuer_name',
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
        'last_reverified_at',
        'dgi_response_payload',
        'registration_source',
        'registered_by_user_id',
        'assisted_by_fraud_flag_id',
        'assistance_notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'purchase_amount' => 'decimal:2',
            'daily_points_capped' => 'boolean',
            'daily_invoice_limit_hit' => 'boolean',
            'dgi_checked_at' => 'datetime',
            'last_reverified_at' => 'datetime',
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

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function assistedByFraudFlag(): BelongsTo
    {
        return $this->belongsTo(FraudFlag::class, 'assisted_by_fraud_flag_id');
    }

    public function fraudFlags(): HasMany
    {
        return $this->hasMany(FraudFlag::class);
    }
}
