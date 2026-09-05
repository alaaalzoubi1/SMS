<?php

namespace App\Models;

use Carbon\Carbon;
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
        'unit_price',
        'start_date',
        'end_date',
        'status',
        'reserved_by_admin',
    ];

    protected $appends = ['days', 'final_price'];

    protected $casts = [
        'unit_price' => 'float',
        'reserved_by_admin' => 'boolean',
    ];

    public function getDaysAttribute(): int
    {
        if ($this->start_date === null) {
            return 0;
        }

        $reference = $this->end_date ?? now();

        return (int) Carbon::parse($this->start_date)
            ->diffInDays(Carbon::parse($reference)->startOfDay()) + 1;
    }

    public function getFinalPriceAttribute(): float
    {
        return round((float) $this->unit_price * $this->days, 2);
    }

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
