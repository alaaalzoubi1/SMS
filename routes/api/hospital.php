<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HospitalController;

// the URL is api/hospital
Route::controller(HospitalController::class)->group(function () {
    Route::get('profile','getProfile');
    Route::post('profile','updateProfile');
    Route::post('change-password','changePassword');
    Route::post('work-schedules','updateWorkSchedules');
});
