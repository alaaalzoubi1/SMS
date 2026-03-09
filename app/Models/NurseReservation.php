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
        'location' => Point::class
    ];
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
    public function cancellation(): HasOne
    {
        return $this->hasOne(NurseCancellation::class, 'reservation_id');
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
            'reason' => $reason
        ]);
    }
    public function rate(): MorphTo
    {
        return $this->morphTo(Rating::class,'reservationable');
    }
}
