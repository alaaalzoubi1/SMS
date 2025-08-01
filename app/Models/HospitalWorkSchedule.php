<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalWorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'day_of_week',

    ];

    public function hospital():BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }
}
