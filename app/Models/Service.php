<?php
namespace App\Models;

use Exception;
use http\Exception\UnexpectedValueException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

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

    /**
     * @throws Exception
     */
    public function uploadIcon($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $allowed = ['png', 'jpg', 'jpeg', 'webp'];
        if (!in_array(strtolower($extension), $allowed)) {
            throw new UnexpectedValueException('امتداد الصورة غير مسموح به. الأنواع المسموحة: ' . implode(', ', $allowed));
        }
        if (!Storage::disk('public')->exists('services')) {
            Storage::disk('public')->makeDirectory('services');
        }
        $fileName = uniqid('service_') . '.' . $extension;
        $tempPath = $file->getPathname();
        $finalStoragePath = storage_path('app/public/services/' . $fileName);


        Image::load($tempPath)
            ->fit(Fit::Max, 100, 100)
            ->optimize()
            ->save($finalStoragePath);

        if ($this->icon && Storage::disk('public')->exists('services/' . $this->icon)) {
            Storage::disk('public')->delete('services/' . $this->icon);
        }

        $this->icon = $fileName;
        $this->saveQuietly();

        return $fileName;
    }

    public function getIconAttribute($value)
    {
        if ($value && Storage::disk('public')->exists('services/' . $value)) {
            return asset(Storage::url('services/' . $value));
        }
        if (Storage::disk('public')->exists('services/default.png')) {
            return asset(Storage::url('services/default.png'));
        }

        return asset('storage/services/default.png');
    }

    protected static function booted()
    {
        static::deleting(function ($service) {
            if ($service->icon && Storage::disk('public')->exists('services/' . $service->icon)) {
                Storage::disk('public')->delete('services/' . $service->icon);
            }
        });
    }

}
