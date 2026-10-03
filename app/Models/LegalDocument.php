<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'content',
        'version',
        'updated_by',
        'updated_by_account_id',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    /**
     * The editor is an Account (admin), not a User.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'updated_by_account_id');
    }

    /**
     * Get the document body for a given locale, falling back to the app's
     * fallback locale (config('app.fallback_locale'), default "en") if the
     * requested language isn't populated yet.
     */
    public function getLocalizedContent(string $locale): ?string
    {
        return $this->content[$locale]
            ?? $this->content[config('app.fallback_locale', 'en')]
            ?? null;
    }
}
