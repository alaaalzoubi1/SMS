<?php

use App\Http\Controllers\Auth\NurseAuthController;
use Illuminate\Support\Facades\Route;

// the URL is api/nurse


Route::post('logout', [NurseAuthController::class, 'logout']);
Route::get('me', [NurseAuthController::class, 'me']);
