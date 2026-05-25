<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceGoalSetting extends Model
{
    protected $table = 'invoice_goal_settings';

    protected $fillable = [
        'is_enabled',
        'goal_value',
        'min_purchase_amount',
        'max_invoice_age_days',
        'one_invoice_per_day',
        'validation_mode',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'one_invoice_per_day' => 'boolean',
            'goal_value' => 'decimal:2',
            'min_purchase_amount' => 'decimal:2',
            'max_invoice_age_days' => 'integer',
        ];
    }
}
