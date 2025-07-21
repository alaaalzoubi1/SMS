<?php

use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\DoctorAuthController;
use App\Http\Controllers\Auth\NurseAuthController;
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
// Throttled routes (limit: 1 request per minute)
Route::middleware('throttle:100,1')->group(function () {

    Route::post('/password/forgot', [ForgotPasswordController::class, 'requestResetCode']);

    Route::prefix('doctor')->group(function () {
        Route::post('register', [DoctorAuthController::class, 'register']);
        Route::post('verifyCode', [DoctorAuthController::class, 'verifyCode']);
        Route::post('login', [DoctorAuthController::class, 'login']);
        Route::post('request-login', [DoctorAuthController::class, 'requestLogin']);
        Route::post('verify-login', [DoctorAuthController::class, 'verifyLogin']);
    });

    Route::prefix('nurse')->group(function () {
        Route::post('register', [NurseAuthController::class, 'register']);
        Route::post('verifyCode', [NurseAuthController::class, 'verifyCode']);
        Route::post('login', [NurseAuthController::class, 'login']);
        Route::post('request-login', [NurseAuthController::class, 'requestLogin']);
        Route::post('verify-login', [NurseAuthController::class, 'verifyLogin']);
    });
});

Route::post('/password/reset', [ForgotPasswordController::class, 'resetPassword']);

Route::post('admin/login', [AdminAuthController::class, 'login']);

Route::get('doctor/specializations',[DoctorAuthController::class,'specializations']);



Route::controller(HospitalController::class)->group(function () {
    Route::get('profile','getProfile');
    Route::put('updateProfile','updateProfile');
    Route::post('change-password','changePassword');
    Route::post('work-schedules','updateWorkSchedules');
});
