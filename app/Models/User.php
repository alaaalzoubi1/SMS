<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class User extends Model
{
    use SoftDeletes,HasFactory;

    protected $fillable = [
        'account_id',
        'full_name',
        'birthdate',
        'age',
        'gender',
        'phone',
    ];

    protected $appends = ['phone_number'];

    protected $casts = [
        'birthdate' => 'date',
    ];

    protected static function booted()
    {
        static::deleting(function ($user) {
            if ($user->isForceDeleting()) {
                $user->ratings()->forceDelete();
            } else {
                $user->ratings()->delete();
            }
        });
    }

    protected function getAgeAttribute()
    {
        if (!empty($this->birthdate)) {
            return $this->birthdate->age;
        }

        return $this->attributes['age'] ?? null;
    }

    protected function setBirthdateAttribute($value)
    {
        $date = $value ? Carbon::parse($value) : null;

        $this->attributes['birthdate'] = $date ? $date->toDateString() : null;
        $this->attributes['age'] = $date ? $date->age : null;
    }

    public function getPhoneNumberAttribute(): ?string
    {
        return $this->phone ?? $this->account?->phone_number;
    }

    public function account():BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    public function nurseReservations(): HasMany
    {
        return $this->hasMany(NurseReservation::class);
    }
    public function hospitalReservations():HasMany
    {
        return $this->hasMany(HospitalServiceReservation::class);
    }
    public function doctorReservations():HasMany
    {
        return $this->hasMany(DoctorReservation::class);
    }
    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

}
