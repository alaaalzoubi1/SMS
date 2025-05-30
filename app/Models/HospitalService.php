<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HospitalService extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'service_id',
        'price',
        'capacity',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }


    public function service():BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
    public function reservations():HasMany
    {
        return $this->hasMany(HospitalServiceReservation::class);
    }
}
