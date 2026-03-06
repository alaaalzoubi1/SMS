<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'reserved_by_admin',
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
    public function cancellation(): HasOne
    {
        return $this->hasOne(DoctorCancellation::class, 'reservation_id');
    }
    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled']);
    }

    public function cancel(string $reason): void
    {
        if (!$this->canBeCancelled()) {
            throw new \DomainException('Reservation cannot be cancelled.');
        }

        $this->status = 'cancelled';
        $this->save();

        $this->cancellation()->create([
            'reason' => $reason
        ]);
    }

}
