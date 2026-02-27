<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NurseService extends Model
{
    use SoftDeletes,HasFactory;

    protected $fillable = [
        'nurse_id',
        'price',
    ];
    protected $appends = ['name'];
    public function nurse():BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }
    public function reservations(): HasMany
    {
        return $this->hasMany(NurseReservation::class, 'nurse_service_id');
    }
    public function services()
    {
        return $this->belongsToMany(Service::class, 'nurse_services')
            ->withPivot('price')
            ->whereNull('nurse_services.deleted_at');
    }

    public function getNameAttribute()
    {
        return $this->service?->service_name;
    }

}
