<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HospitalController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::controller(HospitalController::class)->group(function () {
    Route::get('profile','getProfile');
    Route::put('updateProfile','updateProfile');
    Route::post('change-password','changePassword');
    Route::post('work-schedules','updateWorkSchedules');
});