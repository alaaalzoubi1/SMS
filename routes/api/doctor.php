<?php

use App\Http\Controllers\Auth\DoctorAuthController;
use Illuminate\Support\Facades\Route;

// the URL is api/doctor


Route::post('logout', [DoctorAuthController::class, 'logout']);
Route::get('me', [DoctorAuthController::class, 'me']);
