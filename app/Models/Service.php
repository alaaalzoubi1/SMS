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
        'service_type'
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

}
