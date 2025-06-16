<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Doctor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'specialization',
        'address',
        'age',
        'gender',
        'instructions_before_booking'
    ];

    // Doctor belongs to Account
    public function account() : BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    public function services():HasMany
    {
        return $this->hasMany(DoctorService::class);
    }
    public function doctorWorkSchedule():HasMany
    {
        return $this->hasMany(DoctorWorkSchedule::class);
    }
    public function reservations():HasMany
    {
        return $this->hasMany(DoctorReservation::class);
    }
}
