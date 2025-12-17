<?php

use App\Http\Controllers\Admin\AdminApproveController;
use App\Http\Controllers\Admin\DoctorStatisticsController;
use App\Http\Controllers\Admin\HospitalStatisticsController;
use App\Http\Controllers\Admin\ManageHospitalsAccountsController;
use App\Http\Controllers\Admin\NurseStatisticsController;
use App\Http\Controllers\Admin\UserStatisticsController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpecializationController;

// the URL is api/admin

Route::middleware('throttle:1,0.1')->group(function () {
    Route::post('logout', [AdminAuthController::class, 'logout']);
    Route::get('me', [AdminAuthController::class, 'me']);
    Route::get('approveAccount/{id}', [AdminApproveController::class, 'approve']);
    Route::post('get-pending-accounts', [AdminApproveController::class, 'index']);
    Route::post('create-hospital-account', [ManageHospitalsAccountsController::class, 'createHospitalAccount']);


    Route::prefix('service')->group(function () {
        Route::post('/', [ServiceController::class, 'create'])->name('service.create');
        Route::get('/', [ServiceController::class, 'index'])->name('service.index');
        Route::put('/{service}', [ServiceController::class, 'update'])->name('service.update');
        Route::delete('/{id}', [ServiceController::class, 'destroy'])->name('service.destroy');
        Route::patch('/{id}', [ServiceController::class, 'restore'])->name('service.restore');
        Route::get('/trashed', [ServiceController::class, 'trashed'])->name('service.trashed');
    });

    Route::prefix('specializations')->group(function () {
        Route::get('', [SpecializationController::class, 'index']);
        Route::get('/{id}', [SpecializationController::class, 'show']);
        Route::post('', [SpecializationController::class, 'create']);
        Route::post('/{id}', [SpecializationController::class, 'update']);
        Route::delete('/{id}', [SpecializationController::class, 'destroy']);
    });


    Route::prefix('doctor')->group(function () {
        Route::get('/{id}/license', [DoctorStatisticsController::class, 'getDoctorLicense']);
        Route::get('/all', [DoctorStatisticsController::class, 'doctors']);
        Route::get('/{id}', [DoctorStatisticsController::class, 'doctor']);
        Route::get('/{id}/reservations', [DoctorStatisticsController::class, 'doctorReservations']);

    });
    Route::prefix('hospital')->group(function () {
        Route::get('/all', [HospitalStatisticsController::class, 'hospitals']);
        Route::get('/{id}', [HospitalStatisticsController::class, 'hospital']);
        Route::get('/{id}/reservations', [HospitalStatisticsController::class, 'hospitalReservations']);
    });
    Route::prefix('nurse')->group(function () {
        Route::get('/{id}/license', [NurseStatisticsController::class, 'getNurseLicense']);
        Route::get('/all', [NurseStatisticsController::class, 'nurses']);
        Route::get('/{id}', [NurseStatisticsController::class, 'nurse']);
        Route::get('/{id}/reservations', [NurseStatisticsController::class, 'nurseReservations']);
    });
    Route::prefix('user')->group(function () {
        Route::get('/all', [UserStatisticsController::class, 'users']);
        Route::get('/{id}/reservations', [UserStatisticsController::class, 'userReservations']);
    });
    Route::prefix('provinces')->group(function () {
        Route::get('/', [ProvinceController::class, 'index']);
        Route::patch('/', [ProvinceController::class, 'update']);
        Route::post('/', [ProvinceController::class, 'store']);
        Route::delete('/{id}', [ProvinceController::class, 'destroy']);
    });

    Route::get('entity-rates', [\App\Http\Controllers\RatingController::class, 'entityRatings']);
});
