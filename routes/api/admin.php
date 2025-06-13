<?php

use App\Http\Controllers\Admin\AdminApproveController;
use App\Http\Controllers\Auth\AdminAuthController;
use Illuminate\Support\Facades\Route;

// the URL is api/admin


Route::post('logout', [AdminAuthController::class, 'logout']);
Route::get('me', [AdminAuthController::class, 'me']);
Route::get('approveDoctor/{id}',[AdminApproveController::class,'approveDoctor']);
