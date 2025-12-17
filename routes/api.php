<?php

use App\Http\Controllers\Admin\DoctorStatisticsController;
use App\Http\Controllers\Admin\HospitalStatisticsController;
use App\Http\Controllers\Admin\NurseStatisticsController;
use App\Http\Controllers\Admin\UserStatisticsController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\DoctorAuthController;
use App\Http\Controllers\Auth\HospitalAuthController;
use App\Http\Controllers\Auth\NurseAuthController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\SpecializationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HospitalController;


use App\Http\Controllers\Auth\ForgotPasswordController;

//Route::get('/clear-config', function () {
//    Artisan::call('config:clear');
//    Artisan::call('cache:clear');
//    return 'Cleared!';
//});
//Route::get('/migrate',function (){
//    Artisan::call('migrate:fresh');
//    Artisan::call('db:seed --class=RolesSeeder');
//    Artisan::call('db:seed --class=AdminAccountSeeder');
//    return 'Migrated';
//});
// Throttled routes (limit: 1 request per minute)
Route::middleware('throttle:1,0.1')->group(function () {
    Route::get('provinces', [ProvinceController::class, 'index']);


    Route::post('/password/forgot', [ForgotPasswordController::class, 'requestResetCode']);

    Route::prefix('doctor')->group(function () {
        Route::post('register', [DoctorAuthController::class, 'register']);
//        Route::post('verifyCode', [DoctorAuthController::class, 'verifyCode']);
        Route::post('login', [DoctorAuthController::class, 'login']);
        Route::post('request-login', [DoctorAuthController::class, 'requestLogin']);
        Route::post('verify-login', [DoctorAuthController::class, 'verifyLogin']);
    });

    Route::prefix('nurse')->group(function () {
        Route::post('register', [NurseAuthController::class, 'register']);
//        Route::post('verifyCode', [NurseAuthController::class, 'verifyCode']);
        Route::post('login', [NurseAuthController::class, 'login']);
        Route::post('request-login', [NurseAuthController::class, 'requestLogin']);
        Route::post('verify-login', [NurseAuthController::class, 'verifyLogin']);
    });
    Route::prefix('hospital')->group(function () {
        Route::post('register', [HospitalAuthController::class, 'updateHospitalData']);
//        Route::post('verifyCode', [HospitalAuthController::class, 'verifyCode']);
        Route::post('login', [HospitalAuthController::class, 'login']);
        Route::post('request-login', [HospitalAuthController::class, 'requestLogin']);
        Route::post('verify-login', [HospitalAuthController::class, 'verifyLogin']);
    });
    Route::prefix('user')->group(function (){
        Route::post('register', [UserAuthController::class ,'register']);
        Route::post('login', [UserAuthController::class, 'login']);
        Route::post('request-login', [UserAuthController::class, 'requestLogin']);
        Route::post('verify-login', [UserAuthController::class, 'verifyLogin']);
    });
});


Route::post('/password/reset', [ForgotPasswordController::class, 'resetPassword']);

Route::post('admin/login', [AdminAuthController::class, 'login']);

Route::get('doctor/specializations', [SpecializationController::class, 'index']);
Route::delete('account',[\App\Http\Controllers\AccountController::class,'destroy']);


use App\Http\Controllers\FirebaseTestController;

Route::post('/test-fcm', [FirebaseTestController::class, 'sendTestNotification']);
Route::get('my-rates',[\App\Http\Controllers\RatingController::class,'myRatings']);

