<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NurseSubservice extends Model
{
    use SoftDeletes,HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'price',
    ];

    public function service():BelongsTo
    {
        return $this->belongsTo(NurseService::class, 'service_id');
    }

    public function reservations():BelongsToMany
    {
        return $this->belongsToMany(NurseReservation::class, 'nurse_subservices_nurse_reservations', 'subservice_id', 'nurse_reservation_id');
    }

}
