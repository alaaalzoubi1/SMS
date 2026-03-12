<?php
namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

trait HasActiveAccountScope
{
    protected static function bootHasActiveAccountScope(): void
    {
        static::addGlobalScope('not_suspended', function (Builder $query) {

            if (!auth()->user()->hasRole('admin')) {
                $query->whereHas('account', function ($q) {
                    $q->where('is_suspended', false)
                        ->where(function ($subQuery) {
                            $subQuery->whereNull('subscription_expires_at')
                            ->orWhere('subscription_expires_at', '>=', Carbon::now());
                        });
                });
            }
        });
    }

    public function scopeWithSuspended($query)
    {
        return $query->withoutGlobalScope('not_suspended');
    }
}
