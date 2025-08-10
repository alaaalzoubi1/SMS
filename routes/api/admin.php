<?php

use App\Http\Controllers\Admin\AdminApproveController;
use App\Http\Controllers\Admin\ManageHospitalsAccountsController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpecializationController;

// the URL is api/admin


Route::post('logout', [AdminAuthController::class, 'logout']);
Route::get('me', [AdminAuthController::class, 'me']);
Route::get('approveAccount/{id}',[AdminApproveController::class,'approve']);
Route::post('get-pending-accounts',[AdminApproveController::class,'index']);
Route::post('create-hospital-account',[ManageHospitalsAccountsController::class , 'createHospitalAccount']);


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
