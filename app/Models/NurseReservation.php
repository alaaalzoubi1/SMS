<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NurseReservation extends Model
{
    use SoftDeletes , HasFactory;

    protected $fillable = [
        'user_id',
        'nurse_id',
        'nurse_service_id',
        'reservation_type',
        'location_lat',
        'location_lng',
        'status',
        'note',
        'start_at',
        'end_at',
    ];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nurse():BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }

    public function nurseService():BelongsTo
    {
        return $this->belongsTo(NurseService::class, 'nurse_service_id');
    }

    public function subservices(): BelongsToMany
    {
        return $this->belongsToMany(NurseSubservice::class, 'nurse_subservices_nurse_reservations', 'nurse_reservation_id', 'subservice_id');
    }

}
