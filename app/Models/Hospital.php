<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;

class Hospital extends Model
{
    /** @use HasFactory<\Database\Factories\HospitalFactory> */
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'account_id',
        'address',
        'full_name',
        'unique_code'
    ];
    protected $hidden = [
        'unique_code',
    ];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function account():BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    public function workSchedule():HasMany
    {
        return $this->hasMany(HospitalWorkSchedule::class, 'hospital_id');
    }
    public function services():HasMany
    {
        return $this->hasMany(HospitalService::class,'hospital_id');
    }
    public function services_2():BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'hospital_services', 'hospital_id', 'service_id')
            ->withPivot('price', 'capacity') // Include pivot data (price and capacity)
            ->whereNotNull('hospital_services.price'); // Only include services with a price
    }

}
