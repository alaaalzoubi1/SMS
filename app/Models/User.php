<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'full_name',
        'age',
        'gender',
    ];

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

}
