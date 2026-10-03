<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NurseCancellation extends Model
{
    public const CANCELLED = 'cancelled';
    public const REJECTED  = 'rejected';

    protected $fillable = [
        'reservation_id',
        'reason',
        'status'
    ];

    protected $casts = [
        'status' => 'string',
    ];

    protected $attributes = [
        'status' => self::CANCELLED,
    ];

    public function isRejection(): bool
    {
        return $this->status === self::REJECTED;
    }
}
