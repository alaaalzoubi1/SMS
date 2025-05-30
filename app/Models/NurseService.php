<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NurseService extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nurse_id',
        'name',
        'price',
    ];

    public function nurse():BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }
    public function subservice():HasMany
    {
        return $this->hasMany(NurseSubservice::class);
    }
    public function reservations(): HasMany
    {
        return $this->hasMany(NurseReservation::class, 'nurse_service_id');
    }

}
