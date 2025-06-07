<?php

use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\DoctorAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('admin/login', [AdminAuthController::class, 'login']);
Route::post('doctor/register',[DoctorAuthController::class,'register']);
Route::post('doctor/verifyCode',[DoctorAuthController::class,'verifyCode']);
Route::post('doctor/login',[DoctorAuthController::class,'login']);

