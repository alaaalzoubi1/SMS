<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Real, live counts for the landing page's "stats" section — cached
 * forever and only invalidated by PlatformStatsObserver when a new
 * doctor/nurse/hospital/user registers (see AppServiceProvider). A landing
 * page hit never runs a COUNT() query; a registration event does, once.
 */
class PlatformStatsService
{
    public const CACHE_KEY = 'platform_stats';

    /**
     * @return array{doctors_count:int, nurses_count:int, hospitals_count:int, users_count:int}
     */
    public static function get(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return [
                'doctors_count' => Doctor::count(),
                'nurses_count' => Nurse::count(),
                'hospitals_count' => Hospital::count(),
                'users_count' => User::count(),
            ];
        });
    }
}
