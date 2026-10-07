<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class NurseReservation extends Model
{
    use SoftDeletes , HasFactory , HasSpatial;

    protected $fillable = [
        'user_id',
        'nurse_id',
        'nurse_service_id',
        'reservation_type',
        'location',
        'status',
        'note',
        'start_at',
        'end_at',
        'reserved_by_admin'
    ];
    protected  $casts = [
        'location' => Point::class,
        'price' => 'float',
        'reserved_by_admin' => 'boolean',
    ];

    /**
     * A reservation always covers at least one service, but a client should
     * never have to reach for `services` itself to know how many there are or
     * what they add up to. `nurse_service_id` is legacy (first service only)
     * and stays in the payload for backwards compatibility — these
     * attributes are the ones to build the cart/history UI from.
     */
    protected $appends = [
        'services_count',
        'service_names',
        'services_total_price',
        'cancellation_reason',
        'rejection_reason',
    ];

    public function getServicesCountAttribute(): int
    {
        return $this->relationLoaded('services')
            ? $this->services->count()
            : $this->services()->count();
    }

    public function getServiceNamesAttribute(): array
    {
        if ($this->relationLoaded('services')) {
            return $this->services->map(fn (NurseService $s) => $s->name)->values()->all();
        }

        return $this->services()->get()->map(fn (NurseService $s) => $s->name)->values()->all();
    }

    /**
     * Sum of the per-service price snapshots on the pivot. Falls back to the
     * reservation's own `price` column when the pivot rows are missing, so a
     * legacy single-service reservation still reports a total.
     */
    public function getServicesTotalPriceAttribute(): float
    {
        $services = $this->relationLoaded('services')
            ? $this->services
            : $this->services()->get();

        if ($services->isEmpty()) {
            return round((float) $this->price, 2);
        }

        return round((float) $services->sum(fn (NurseService $s) => $s->pivot->price ?? $s->price), 2);
    }

    /**
     * Build a display string listing every service on the reservation, e.g.
     * "حقن، قياس ضغط". Used for notifications where the singular
     * `nurseService` used to leak only the first entry.
     */
    public function servicesLabel(string $separator = '، '): string
    {
        $names = array_filter($this->service_names);

        if (empty($names)) {
            return 'خدمة';
        }

        return implode($separator, $names);
    }

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
        return $this->belongsTo(NurseService::class)->withTrashed();
    }
    public function services():BelongsToMany
    {
        return $this->belongsToMany(NurseService::class, 'nurse_reservation_services')
            ->withTrashed()
            ->withPivot('price');
    }
    public function cancellation(): HasOne
    {
        return $this->hasOne(NurseCancellation::class, 'reservation_id');
    }

    /**
     * Same row, filtered to the rejection case. A rejection and a
     * cancellation both live in `nurse_cancellations` (the table predates
     * the split), so they are told apart by the `status` column.
     */
    public function rejection(): HasOne
    {
        return $this->hasOne(NurseCancellation::class, 'reservation_id')
            ->where('status', 'rejected');
    }

    public function getCancellationReasonAttribute(): ?string
    {
        if ($this->status === 'rejected') {
            return null;
        }

        return $this->cancellation?->reason;
    }

    public function getRejectionReasonAttribute(): ?string
    {
        if ($this->status !== 'rejected') {
            return null;
        }

        return $this->cancellation?->reason;
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['completed', 'rejected', 'cancelled']);
    }

    public function cancel(string $reason): void
    {
        if (!$this->canBeCancelled()) {
            throw new \DomainException('Reservation cannot be cancelled.');
        }

        $this->status = 'cancelled';
        $this->save();

        $this->cancellation()->create([
            'reason' => $reason,
            'status' => NurseCancellation::CANCELLED,
        ]);
    }

    /**
     * Nurse-side refusal. Kept separate from cancel() so the reason is stored
     * (and later read back) under a `rejected` marker instead of being
     * indistinguishable from a cancellation.
     */
    public function reject(string $reason): void
    {
        if ($this->status !== 'pending') {
            throw new \DomainException('Only a pending reservation can be rejected.');
        }

        $this->status = 'rejected';
        $this->save();

        $this->cancellation()->updateOrCreate(
            ['reservation_id' => $this->id],
            ['reason' => $reason, 'status' => NurseCancellation::REJECTED]
        );
    }

    public function rate(): MorphTo
    {
        return $this->morphTo(Rating::class,'reservationable');
    }
}
