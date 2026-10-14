<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Account extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'email',
        'password',
        'phone_number',
        'fcm_token',
        'email_verified_at',
        'is_approved',
        'is_suspended',
        'verification_code',
        'subscription_expires_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Without this cast `subscription_expires_at` came back as a plain string,
     * so AdminApproveController::extendSubscription() crashed on
     * `$currentExpiry->isFuture()` for any account that already had an expiry
     * (e.g. every account that received the automatic trial).
     */
    protected $casts = [
        'subscription_expires_at' => 'datetime:Y-m-d H:i:s', // same string format the API returned before
        'is_suspended' => 'boolean',
    ];

    public function nurse(): HasOne
    {
        return $this->hasOne(Nurse::class);
    }
    public function doctor():HasOne
    {
        return $this->hasOne(Doctor::class);
    }
    public function hospital():HasOne
    {
        return $this->hasOne(Hospital::class);
    }
    public function user():HasOne
    {
        return $this->hasOne(User::class);
    }
    public function scopeActive($query)
    {
        return $query
            ->where('is_suspended', false)
            ->where('is_approved', 'approved');
    }
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
