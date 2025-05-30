<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HospitalServiceReservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'hospital_service_id',
        'hospital_id',
        'start_date',
        'end_date',
        'status',
    ];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hospitalService():BelongsTo
    {
        return $this->belongsTo(HospitalService::class);
    }

    public function hospital():BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
