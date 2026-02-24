<?php

namespace App\Providers;

use Carbon\Laravel\ServiceProvider;
use Illuminate\Support\Facades\Route;

class SuspendedRouteServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Route::prefix('api/doctor')
            ->middleware('ensure.not.suspended')
            ->group(base_path('routes/api/doctor.php'));

        Route::prefix('api/nurse')
            ->middleware('ensure.not.suspended')
            ->group(base_path('routes/api/nurse.php'));

        Route::prefix('api/user')
            ->middleware('ensure.not.suspended')
            ->group(base_path('routes/api/user.php'));

        Route::prefix('api/hospital')
            ->middleware('ensure.not.suspended')
            ->group(base_path('routes/api/hospital.php'));
    }
}
