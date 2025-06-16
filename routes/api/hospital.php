<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\HospitalServiceController;
use App\Http\Controllers\HospitalWorkScheduleController;

// the URL is api/hospital
Route::controller(HospitalController::class)->group(function () {
    Route::get('profile','getProfile');
    Route::post('profile','updateProfile');
    Route::post('change-password','changePassword');
    Route::post('work-schedules','updateWorkSchedules');
});

Route::controller(HospitalServiceController::class)->group(function () {
    Route::get('services','index');
    Route::post('services','store');
    Route::get('services/{id}','show');
    Route::put('services/{id}','update');
    Route::delete('services/{id}','destroy');
});

Route::controller(HospitalWorkScheduleController::class)->group(function () {
    Route::get('work-schedules','index');
    Route::post('work-schedules','store');
    Route::get('work-schedules/{id}','show');
    Route::put('work-schedules/{id}','update');
    Route::delete('work-schedules/{id}','destroy');
});
