<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Specialization extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_ar',
        'image'
    ];

    /**
     * Full public URL of the image. `image` only holds the file name, so the
     * apps had to guess the storage path.
     */
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return asset(Storage::url('specializations/' . $this->image));
    }

    public static function createSpecialization($data)
    {
        return self::create($data);
    }

    public static function updateSpecialization($id, $data)
    {
        $specialization = self::findOrFail($id);
        $specialization->update($data);
        return $specialization;
    }

    public static function deleteSpecialization($id)
    {
        $specialization = self::findOrFail($id);
        return $specialization->delete();
    }
    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }
}
