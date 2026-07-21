<?php

use Illuminate\Support\Facades\Route;

// This backend is API-only — the frontend (including the landing page) is a
// separate React app that consumes routes/api.php. See LandingPageController
// for the aggregate GET /api/landing endpoint it's built around.
Route::get('/', function () {
    return response()->json(['status' => 'ok', 'service' => config('app.name')]);
});
