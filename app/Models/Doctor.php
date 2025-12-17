<?php

namespace App\Models;

use App\Enums\SpecializationType;
use App\Models\Scopes\ProvinceScope;
use App\Rateable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class Doctor extends Model
{
    use HasFactory, SoftDeletes,Rateable,HasSpatial;

    protected $fillable = [
        'account_id',
        'full_name',
        'profile_description',
        'address',
        'age',
        'gender',
        'specialization_id',
        'license_image_path',
        'location',
        'profile_image_path',
        'province_id'
    ];
    protected $casts = [
        'specialization_type' => SpecializationType::class,
        'location' => Point::class
    ];
    protected $hidden = [
        'license_image_path'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new ProvinceScope);
    }


    // Doctor belongs to Account
    public function account() : BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    public function services():HasMany
    {
        return $this->hasMany(DoctorService::class);
    }
    public function doctorWorkSchedule():HasMany
    {
        return $this->hasMany(DoctorWorkSchedule::class);
    }
    public function reservations():HasMany
    {
        return $this->hasMany(DoctorReservation::class);
    }
    public function specialization():BelongsTo
    {
        return $this->BelongsTo(Specialization::class);
    }
    public function scopeApproved($query)
    {
        return $query->whereHas('account', function ($q) {
            $q->where('is_approved', 'approved');
        });
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
