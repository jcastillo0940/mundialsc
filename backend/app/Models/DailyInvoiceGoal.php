<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyInvoiceGoal extends Model
{
    protected $table = 'daily_invoice_goals';

    protected $fillable = [
        'user_id',
        'phase_id',
        'invoice_number',
        'purchase_amount',
        'invoice_date',
        'goal_points_awarded',
        'validation_status',
        'validation_notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'goal_points_awarded' => 'decimal:2',
            'purchase_amount' => 'decimal:2',
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TournamentPhase::class, 'phase_id');
    }
}
