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
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    /**
     * Inclusive day count: a reservation running from the 1st to the 5th is
     * five days, not four.
     *
     * Both ends are normalized to the start of the day. Previously only the
     * reference date was, so a start_date carrying a time (e.g. the `now()`
     * written on confirmation) lost most of its first day and the total came
     * out one day short.
     */
    public function getDaysAttribute(): int
    {
        if ($this->start_date === null) {
            return 0;
        }

        $start = Carbon::parse($this->start_date)->startOfDay();
        $reference = $this->end_date !== null
            ? Carbon::parse($this->end_date)->startOfDay()
            : Carbon::now()->startOfDay();

        // A future end_date (or a bad one) must never produce a negative
        // count, otherwise final_price becomes negative.
        if ($reference->lt($start)) {
            $reference = $start;
        }

        return (int) $start->diffInDays($reference) + 1;
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
