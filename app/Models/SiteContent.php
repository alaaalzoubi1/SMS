<?php

namespace App\Models;

use App\Enums\SiteContentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'type',
        'value',
        'images',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'value' => 'array',
        'images' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_urls',
    ];

    public function scopeSections($query)
    {
        return $query->where('type', SiteContentType::SECTION->value);
    }

    public function scopeSettings($query)
    {
        return $query->where('type', SiteContentType::SETTING->value);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Full public URLs for every path stored in `images`, so the frontend
     * never has to know about the storage disk layout.
     *
     * @return array<int, string>
     */
    public function getImageUrlsAttribute(): array
    {
        if (empty($this->images)) {
            return [];
        }

        return collect($this->images)
            ->filter()
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->values()
            ->all();
    }

    /**
     * Get this section's localized value for a given locale, falling back
     * to the app's fallback locale, then to whatever is first available.
     * Returns the raw value untouched for "setting" rows (e.g. theme),
     * since those aren't bilingual.
     */
    public function localized(string $locale): mixed
    {
        if ($this->type === SiteContentType::SETTING->value) {
            return $this->value;
        }

        if (! is_array($this->value)) {
            return $this->value;
        }

        return $this->value[$locale]
            ?? $this->value[config('app.fallback_locale', 'en')]
            ?? collect($this->value)->first();
    }
}
