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
use App\Http\Controllers\ServiceController;
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

Route::prefix('nurses')->group(function (){
    Route::get('/',[NurseController::class,'listForUsers']);
    Route::get('services',[NurseServiceController::class,'getFilteredServices']);
    Route::get('nearest',[NurseController::class,'getNearestNurses']);
    Route::get('{nurseId}/services',[NurseServiceController::class,'getNurseServicesWithSubservices']);
    Route::post('reserve',[NurseReservationController::class,'store']);

});
Route::prefix('hospitals')->group(function (){
    Route::get('/',[HospitalController::class,'getHospitalsWithServices']);
    Route::get('/services',[ServiceController::class,'getServicesWithHospitals']);
    Route::get('{hospitalId}/available-dates',[HospitalWorkScheduleController::class,'getAvailableDates']);
    Route::get('/{hospitalId}',[HospitalController::class,'getHospitalServices']);
    Route::post('/make-reservation', [HospitalServiceReservationController::class, 'makeReservation']);

});
