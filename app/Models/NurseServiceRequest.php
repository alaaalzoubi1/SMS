<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NurseServiceRequest extends Model
{
    protected $fillable = [
        'nurse_id',
        'service_id',
        'price',
        'certificate_path',
        'status',
        'admin_note'
    ];

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
