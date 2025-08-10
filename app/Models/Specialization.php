<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specialization extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_ar',
        'image'
    ];

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
