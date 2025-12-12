<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalCancellation extends Model
{
    protected $fillable = [
        'reservation_id',
        'reason',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(HospitalServiceReservation::class, 'reservation_id');
    }
}
