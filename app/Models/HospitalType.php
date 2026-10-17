<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class HospitalType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_ar',
        'name_en',
        'icon',
        'sort_order',
    ];

    protected $hidden = ['deleted_at'];

    protected $appends = ['icon_url'];

    public function hospitals(): HasMany
    {
        return $this->hasMany(Hospital::class);
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon ? asset(Storage::url('hospital_types/' . $this->icon)) : null;
    }
}
