<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone_number',
        'fcm_token',
        'email_verified_at',
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

}
