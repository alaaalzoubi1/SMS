<?php

namespace App\Models;

use App\Models\Scopes\ProvinceScope;
use App\Rateable;
use Database\Factories\NurseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;


class Nurse extends Model
{
    /** @use HasFactory<NurseFactory> */
    use HasFactory,SoftDeletes,HasSpatial,Rateable;
    protected $fillable = [
        'account_id',
        'full_name',
        'address',
        'graduation_type',
        'longitude',
        'latitude',
        'location',
        'age',
        'gender',
        'profile_description',
        'license_image_path',
        'profile_image_path',
        'province_id'
    ];

    protected  $casts = [
        'location' => Point::class
    ];

    protected $hidden = [
        'license_image_path'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new ProvinceScope);
    }


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
    public function scopeWithDistance($query, float $latitude, float $longitude)
    {
        return $query->select('*')
            ->selectRaw("(
            6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )
        ) AS distance", [$latitude, $longitude, $latitude]);
    }
    public function scopeActive($query)
    {
        return $query->where('is_active',true);
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
