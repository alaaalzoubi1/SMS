<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\HospitalServiceController;

// the URL is api/hospital
Route::controller(HospitalController::class)->group(function () {
    Route::get('profile','getProfile');
    Route::post('profile','updateProfile');
    Route::post('change-password','changePassword');
    Route::post('work-schedules','updateWorkSchedules');
});

Route::resource('services', HospitalServiceController::class)->except(['create', 'edit']);