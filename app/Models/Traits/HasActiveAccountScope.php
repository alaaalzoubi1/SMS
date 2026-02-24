<?php
namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasActiveAccountScope
{
    protected static function bootHasActiveAccountScope(): void
    {
        static::addGlobalScope('not_suspended', function (Builder $query) {

            if (!auth()->user()->hasRole('admin')) {
                $query->whereHas('account', function ($q) {
                    $q->where('is_suspended', false);
                });
            }
        });
    }

    public function scopeWithSuspended($query)
    {
        return $query->withoutGlobalScope('not_suspended');
    }
}
