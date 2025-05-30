<?php

namespace App\Models;

use Database\Factories\NurseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nurse extends Model
{
    /** @use HasFactory<NurseFactory> */
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'account_id',
        'specialization',
        'study_stage',
        'longitude',
        'latitude',
        'age',
        'gender',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(NurseService::class, 'nurse_id');
    }
    public function reservations(): HasMany
    {
        return $this->hasMany(NurseReservation::class);
    }

}
