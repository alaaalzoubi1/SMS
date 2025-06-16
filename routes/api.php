<?php

use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\DoctorAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HospitalController;


use App\Http\Controllers\Auth\ForgotPasswordController;

Route::get('/clear-config', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    return 'Cleared!';
});
Route::middleware('throttle:1,5')->group(function () {
    Route::post('/password/forgot', [ForgotPasswordController::class, 'requestResetCode']);
    Route::post('doctor/request-login', [DoctorAuthController::class, 'requestLogin']);

});
Route::post('/password/reset', [ForgotPasswordController::class, 'resetPassword']);

Route::post('admin/login', [AdminAuthController::class, 'login']);
Route::post('doctor/register',[DoctorAuthController::class,'register']);
Route::post('doctor/verifyCode',[DoctorAuthController::class,'verifyCode']);
Route::post('doctor/login',[DoctorAuthController::class,'login']);

Route::post('doctor/verify-login', [DoctorAuthController::class, 'verifyLogin']);


Route::controller(HospitalController::class)->group(function () {
    Route::get('profile','getProfile');
    Route::put('updateProfile','updateProfile');
    Route::post('change-password','changePassword');
    Route::post('work-schedules','updateWorkSchedules');
});
