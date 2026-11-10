<?php

namespace App\Observers;

use App\Models\Account;
use App\Services\PlatformStatsService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Attached to Doctor, Nurse, Hospital, User and Account (see
 * AppServiceProvider). Forgets the landing page counts whenever one of
 * them could change, so the next landing page request rebuilds them once.
 * Runs after commit so a request in flight can't re-cache the old numbers.
 */
class PlatformStatsObserver implements ShouldHandleEventsAfterCommit
{
    public function created($model): void
    {
        PlatformStatsService::forget();
    }

    public function updated($model): void
    {
        // Profiles: nothing counted changes on an ordinary edit.
        // Accounts: approval / rejection / suspension moves the numbers.
        if ($model instanceof Account && $model->wasChanged(['is_approved', 'is_suspended'])) {
            PlatformStatsService::forget();
        }
    }

    public function deleted($model): void
    {
        PlatformStatsService::forget();
    }

    public function restored($model): void
    {
        PlatformStatsService::forget();
    }
}
