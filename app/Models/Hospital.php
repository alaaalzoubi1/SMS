<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends Model
{
    /** @use HasFactory<\Database\Factories\HospitalFactory> */
    use HasFactory;
    protected $fillable = [
        'account_id',
        'address'
    ];

    public function account():BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    public function workSchedule():HasMany
    {
        return $this->hasMany(HospitalWorkSchedule::class, 'hospital_id');
    }
}
