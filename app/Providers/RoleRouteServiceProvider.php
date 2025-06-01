<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RoleRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/admin')
            ->middleware(['api', 'role:admin'])
            ->group(base_path('routes/api/admin.php'));

        Route::prefix('api/doctor')
            ->middleware(['api', 'role:doctor'])
            ->group(base_path('routes/api/doctor.php'));

        Route::prefix('api/nurse')
            ->middleware(['api', 'role:nurse'])
            ->group(base_path('routes/api/nurse.php'));

        Route::prefix('api/user')
            ->middleware(['api', 'role:user'])
            ->group(base_path('routes/api/user.php'));

        Route::prefix('api/hospital')
            ->middleware(['api', 'role:hospital'])
            ->group(base_path('routes/api/hospital.php'));
    }
}
