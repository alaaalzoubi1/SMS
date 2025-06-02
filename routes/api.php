<?php

use App\Http\Controllers\Auth\AdminAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('admin/login', [AdminAuthController::class, 'login']);


