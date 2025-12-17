<?php

use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DoctorReservationController;
use App\Http\Controllers\DoctorServiceController;
use App\Http\Controllers\DoctorWorkScheduleController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\HospitalServiceReservationController;
use App\Http\Controllers\HospitalWorkScheduleController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\NurseReservationController;
use App\Http\Controllers\NurseServiceController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

// the URL is api/user
Route::middleware('throttle:1,0.1')->group(function () {
    Route::get('logout', [UserAuthController::class, 'logout']);
    Route::get('me', [UserAuthController::class, 'me']);
    Route::post('updateProfile', [UserAuthController::class, 'updateProfile']);

    Route::prefix('doctors')->group(function () {
        Route::post('/', [DoctorController::class, 'listForUsers']);
        Route::get('{doctor_id}/services', [DoctorServiceController::class, 'getDoctorServices']);
        Route::get('{doctor_id}/available-dates', [DoctorWorkScheduleController::class, 'getAvailableDates']);
        Route::post('reserve', [DoctorReservationController::class, 'reserve']);
        Route::get('nearest', [DoctorController::class, 'getNearestDoctors']);
    });

    Route::prefix('nurses')->group(function () {
        Route::get('/', [NurseController::class, 'listForUsers']);
        Route::get('services', [NurseServiceController::class, 'getFilteredServices']);
        Route::get('nearest', [NurseController::class, 'getNearestNurses']);
        Route::get('{nurseId}/services', [NurseServiceController::class, 'getNurseServicesWithSubservices']);
        Route::post('reserve', [NurseReservationController::class, 'store']);
    });
    Route::prefix('hospitals')->group(function () {
        Route::get('/', [HospitalController::class, 'getHospitalsWithServices']);
        Route::get('nearest', [HospitalController::class, 'getNearestHospitals']);
        Route::get('/services', [ServiceController::class, 'getServicesWithHospitals']);
        Route::get('{hospitalId}/available-dates', [HospitalWorkScheduleController::class, 'getAvailableDates']);
        Route::get('/{hospitalId}', [HospitalController::class, 'getHospitalServices']);
        Route::post('/make-reservation', [HospitalServiceReservationController::class, 'makeReservation']);


    });
    Route::prefix('ratings')->group(function () {
        Route::post('', [RatingController::class, 'store']);
    });
    Route::get('my-reservations', [\App\Http\Controllers\UserReservationsController::class, 'myReservations']);
});
