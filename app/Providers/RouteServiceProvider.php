<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/admin')
            ->middleware(['auth:api', 'ensure.not.suspended', 'role:admin'])
            ->group(base_path('routes/api/admin.php'));

        Route::prefix('api/doctor')
            ->middleware(['auth:api', 'ensure.not.suspended', 'role:doctor'])
            ->group(base_path('routes/api/doctor.php'));

        Route::prefix('api/nurse')
            ->middleware(['auth:api','ensure.not.suspended', 'role:nurse'])
            ->group(base_path('routes/api/nurse.php'));

        Route::prefix('api/user')
            ->middleware(['auth:api', 'ensure.not.suspended', 'role:user'])
            ->group(base_path('routes/api/user.php'));

        Route::prefix('api/hospital')
            ->middleware(['auth:api', 'ensure.not.suspended', 'role:hospital'])
            ->group(base_path('routes/api/hospital.php'));
    }
}
