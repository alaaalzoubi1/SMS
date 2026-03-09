<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HospitalServiceReservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'hospital_service_id',
        'hospital_id',
        'start_date',
        'end_date',
        'status',
        'reserved_by_admin',
    ];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hospitalService():BelongsTo
    {
        return $this->belongsTo(HospitalService::class);
    }

    public function hospital():BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
    public function cancellation(): HasOne
    {
        return $this->hasOne(HospitalCancellation::class, 'reservation_id');
    }
    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['finished', 'cancelled','confirmed']);
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
    public function rate(): MorphTo
    {
        return $this->morphTo(Rating::class,'reservationable');
    }
}
