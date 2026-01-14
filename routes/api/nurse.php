<?php

use App\Http\Controllers\Auth\NurseAuthController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\NurseReservationController;
use App\Http\Controllers\NurseServiceController;
use App\Http\Controllers\NurseSubsercviceController;
use App\Http\Controllers\ProvinceController;
use Illuminate\Support\Facades\Route;

// the URL is api/nurse

    Route::post('logout', [NurseAuthController::class, 'logout']);
    Route::get('me', [NurseAuthController::class, 'me']);
    Route::post('updateProfile', [NurseAuthController::class, 'updateProfile']);
    Route::get('activate-deactivate', [NurseController::class, 'activate']);
    Route::patch('refresh-location',[NurseController::class,'refreshLocation']);
    Route::prefix('services')->group(function () {
        Route::get('/', [NurseServiceController::class, 'index']);
        Route::post('/', [NurseServiceController::class, 'store']);
        Route::post('/{id}', [NurseServiceController::class, 'update']);
        Route::delete('/{id}', [NurseServiceController::class, 'destroy']);
    });

    Route::prefix('sub-services')->group(function () {
        Route::get('/{service_id}', [NurseSubsercviceController::class, 'index']);
        Route::post('/', [NurseSubsercviceController::class, 'store']);
        Route::post('/{id}', [NurseSubsercviceController::class, 'update']);
        Route::delete('/{id}', [NurseSubsercviceController::class, 'destroy']);
    });

    Route::prefix('reservations')->group(callback: function () {
        Route::get('/', [NurseReservationController::class, 'index']);
        Route::patch('/updateStatus/{id}', [NurseReservationController::class, 'updateStatus']);

    });
