<?php


use App\Http\Controllers\Auth\HospitalAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\HospitalServiceController;
use App\Http\Controllers\HospitalWorkScheduleController;
use App\Http\Controllers\HospitalServiceReservationController;

// the URL is api/hospital
Route::post('logout', [HospitalAuthController::class, 'logout']);
Route::get('me', [HospitalAuthController::class, 'me']);
Route::post('edit-profile',[HospitalAuthController::class,'editProfile']);

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

Route::controller(HospitalServiceReservationController::class)->group(function () {
    Route::get('reservations','index'); //all reservations
    Route::get('reservations/trashed', [HospitalServiceReservationController::class, 'trashed']); // view deleted reservations
    Route::get('reservations/{id}','show'); // view one reservations
    Route::patch('reservations/{id}/status','updateStatus'); // update reservations status
    Route::delete('reservations/{id}','destroy'); // delete (Soft delete)
    Route::patch('reservations/{id}/restore','restore'); // Recover a deleted reservation
});
