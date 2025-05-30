<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doctor_id',
        'name',
        'price',
        'duration_minutes',
    ];

    public function doctor():BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
    public function reservations():HasMany
    {
        return $this->hasMany(DoctorReservation::class);
    }
}

