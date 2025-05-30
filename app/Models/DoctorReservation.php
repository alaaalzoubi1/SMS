<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorReservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'doctor_service_id',
        'doctor_id',
        'date',
        'end_date',
        'start_time',
        'end_time',
        'status',
    ];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doctor():BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function doctorService():BelongsTo
    {
        return $this->belongsTo(DoctorService::class);
    }
}
