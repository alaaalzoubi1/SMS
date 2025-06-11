<?php

use App\Http\Controllers\Auth\DoctorAuthController;
use App\Http\Controllers\DoctorReservationController;
use App\Http\Controllers\DoctorServiceController;
use App\Http\Controllers\DoctorWorkScheduleController;
use Illuminate\Support\Facades\Route;

// the base URL is api/doctor


Route::post('logout', [DoctorAuthController::class, 'logout']);
Route::get('me', [DoctorAuthController::class, 'me']);


Route::prefix('schedules')->group(function () {
    Route::post('', [DoctorWorkScheduleController::class, 'store']);
    Route::post('/{id}', [DoctorWorkScheduleController::class, 'update']);
    Route::delete('/{id}', [DoctorWorkScheduleController::class, 'destroy']);
    Route::patch('/restore/{id}', [DoctorWorkScheduleController::class, 'restore']);
    Route::get('/trashed', [DoctorWorkScheduleController::class, 'trashed']);
    Route::get('', [DoctorWorkScheduleController::class, 'mySchedules']);
});


Route::prefix('services')->group(function () {
    Route::get('/', [DoctorServiceController::class, 'index']);
    Route::get('/trashed', [DoctorServiceController::class, 'trashed']);
    Route::post('/', [DoctorServiceController::class, 'store']);
    Route::post('/{id}', [DoctorServiceController::class, 'update']);
    Route::delete('/{id}', [DoctorServiceController::class, 'destroy']);
    Route::patch('/restore/{id}', [DoctorServiceController::class, 'restore']);
});

Route::prefix('reservations')->group(callback: function (){
    Route::post('/',[DoctorReservationController::class,'createStaticReservation']);
    Route::get('/',[DoctorReservationController::class,'index']);
});
