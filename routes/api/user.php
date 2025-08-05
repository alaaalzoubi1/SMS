<?php

use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DoctorReservationController;
use App\Http\Controllers\DoctorServiceController;
use App\Http\Controllers\DoctorWorkScheduleController;
use Illuminate\Support\Facades\Route;

// the URL is api/user

Route::get('logout', [UserAuthController::class, 'logout']);
Route::get('me', [UserAuthController::class, 'me']);
Route::post('updateProfile',[UserAuthController::class,'updateProfile']);

Route::prefix('doctors')->group(function (){
    Route::post('/',[DoctorController::class,'listForUsers']);
    Route::get('{doctor_id}/services',[DoctorServiceController::class,'getDoctorServices']);
    Route::get('{doctor_id}/available-dates',[DoctorWorkScheduleController::class,'getAvailableDates']);
    Route::post('reserve',[DoctorReservationController::class,'reserve']);
});
