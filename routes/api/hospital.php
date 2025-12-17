<?php


use App\Http\Controllers\Auth\HospitalAuthController;
use App\Http\Controllers\ProvinceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\HospitalServiceController;
use App\Http\Controllers\HospitalWorkScheduleController;
use App\Http\Controllers\HospitalServiceReservationController;
Route::middleware('throttle:1,0.1')->group(function () {
// the URL is api/hospital
    Route::post('logout', [HospitalAuthController::class, 'logout']);
    Route::get('me', [HospitalAuthController::class, 'me']);
    Route::post('edit-profile', [HospitalAuthController::class, 'editProfile']);


    Route::prefix('service')->controller(HospitalServiceController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    Route::prefix('work-schedules')->controller(HospitalWorkScheduleController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::delete('/{id}', 'destroy');
    });

    Route::prefix('reservations')->controller(HospitalServiceReservationController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/trashed', 'trashed');
        Route::get('/{id}', 'show');
        Route::patch('/{id}/status', 'updateStatus');
        Route::delete('/{id}', 'destroy');
        Route::patch('/{id}/restore', 'restore');
    });
});
