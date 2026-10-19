<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    public const TYPE_HOSPITAL_RESERVATION = 'hospital_reservation';
    public const TYPE_HOSPITAL_NO_VACANCY  = 'hospital_no_vacancy';
    /** Status update on an admin-made reservation whose patient has no account. */
    public const TYPE_ADMIN_RESERVATION_UPDATE = 'admin_reservation_update';

    protected $fillable = [
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    protected $appends = ['is_read'];

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }
}
