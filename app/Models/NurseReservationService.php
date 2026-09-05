<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NurseReservationService extends Model
{
    protected $fillable = [
        'nurse_reservation_id',
        'nurse_service_id',
        'price',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    public function nurseReservation(): BelongsTo
    {
        return $this->belongsTo(NurseReservation::class);
    }

    public function nurseService(): BelongsTo
    {
        return $this->belongsTo(NurseService::class)->withTrashed();
    }
}