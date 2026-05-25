<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'branch_id',
        'role',
        'full_name',
        'cedula',
        'document_type',
        'email',
        'google_id',
        'phone',
        'avatar_path',
        'password_hash',
        'is_active',
        'birthdate',
        'resides_in_panama',
        'is_employee',
        'accepted_terms_at',
        'registration_completed_at',
        'predictions_completed_at',
        'group_stage_goal_prediction',
        'disqualified_at',
        'disqualification_reason',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'birthdate' => 'date',
            'resides_in_panama' => 'boolean',
            'is_employee' => 'boolean',
            'accepted_terms_at' => 'datetime',
            'registration_completed_at' => 'datetime',
            'predictions_completed_at' => 'datetime',
            'group_stage_goal_prediction' => 'integer',
            'disqualified_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(RegisteredInvoice::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }
}
