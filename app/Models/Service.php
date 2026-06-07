<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'service_name',
        'service_type',
        'requires_certificate',
        'icon'
    ];
    protected $hidden = ['service_type'];

    public function hospitalServices():HasMany
    {
        return $this->hasMany(HospitalService::class);
    }
    public function hospitals()
    {
        return $this->belongsToMany(Hospital::class, 'hospital_services', 'service_id', 'hospital_id')
            ->where('service_type','hospital')
            ->withPivot('price', 'capacity') // To access the price and capacity from the pivot table
            ->whereNotNull('hospital_services.price'); // Only include hospitals where the price is not null
    }
    public function scopeForNurses($query)
    {
        return $query->where('service_type', 'nurse');
    }

    public function scopeForHospitals($query)
    {
        return $query->where('service_type', 'hospital');
    }
    public function getIconAttribute($value): string
    {
        if ($value) {
            return $value;
        }

        return $this->service_type === 'hospital'
            ? 'fas fa-hospital'
            : 'fas fa-user-nurse';
    }

    protected static function booted()
    {
        static::creating(function ($service) {
            if (is_null($service->icon)) {
                $service->icon = $service->service_type === 'hospital'
                    ? 'fas fa-hospital'
                    : 'fas fa-user-nurse';
            }
        });
    }

}
