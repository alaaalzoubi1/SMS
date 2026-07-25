<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;

/**
 * Attached to Doctor, Nurse, Hospital, and User (see AppServiceProvider).
 * The landing page's "stats" numbers (doctor count, nurse count, etc.) are
 * cached forever and only recomputed the moment a new one registers —
 * never on a page read — so a busy landing page never triggers a COUNT()
 * query. See PlatformStatsService.
 */
class PlatformStatsObserver
{
    public function created($model): void
    {
        Cache::forget('platform_stats');
    }
}
