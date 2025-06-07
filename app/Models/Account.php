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
        'full_name',
        'email',
        'password',
        'phone_number',
        'fcm_token',
        'email_verified_at',
        'is_approved',
        'verification_code'
    ];

    protected $hidden = [
        'password',
        'remember_token',
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
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
