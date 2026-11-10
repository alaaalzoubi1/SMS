<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Public counts for the landing page — cached forever and only rebuilt
 * when PlatformStatsObserver forgets the key (a provider/patient is
 * created, deleted or restored, or an account is approved, rejected,
 * suspended or deleted). A landing page hit never runs a COUNT() query.
 *
 * Only real, live accounts are counted: approved, not suspended, and
 * neither the profile nor the account soft-deleted. Patients created by
 * the admin without an account (account_id NULL) are not "registered
 * users" and are left out.
 */
class PlatformStatsService
{
    public const CACHE_KEY = 'platform_stats';

    /**
     * @return array{doctors_count:int, nurses_count:int, hospitals_count:int, users_count:int}
     */
    public static function get(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => [
            'doctors_count' => self::countActive('doctors'),
            'nurses_count' => self::countActive('nurses'),
            'hospitals_count' => self::countActive('hospitals'),
            'users_count' => self::countActive('users'),
        ]);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * One indexed join + COUNT per table (accounts.is_approved is indexed).
     * Query builder on purpose: the models' "not_suspended" global scope
     * depends on who is logged in, and the landing page is public.
     */
    private static function countActive(string $table): int
    {
        return DB::table($table)
            ->join('accounts', 'accounts.id', '=', "{$table}.account_id")
            ->whereNull("{$table}.deleted_at")
            ->whereNull('accounts.deleted_at')
            ->where('accounts.is_approved', 'approved')
            ->where('accounts.is_suspended', false)
            ->count();
    }
}
