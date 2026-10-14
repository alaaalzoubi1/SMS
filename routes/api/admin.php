<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminApproveController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\ProviderStatisticsController;
use App\Http\Controllers\Admin\AdminReservationController;
use App\Http\Controllers\Admin\BroadcastNotificationController;
use App\Http\Controllers\Admin\DoctorStatisticsController;
use App\Http\Controllers\Admin\HospitalStatisticsController;
use App\Http\Controllers\Admin\ManageHospitalsAccountsController;
use App\Http\Controllers\Admin\NurseStatisticsController;
use App\Http\Controllers\Admin\UserStatisticsController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\ContactInfoController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\NurseServiceRequestController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SiteContentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpecializationController;

// the URL is api/admin

Route::post('logout', [AdminAuthController::class, 'logout']);
Route::get('me', [AdminAuthController::class, 'me']);
Route::get('approveAccount/{id}', [AdminApproveController::class, 'approve']);
Route::patch('toggleSuspension',[AdminApproveController::class,'toggleSuspension']);
Route::post('get-pending-accounts', [AdminApproveController::class, 'index']);
Route::post('create-hospital-account', [ManageHospitalsAccountsController::class, 'createHospitalAccount']);
Route::post('broadcast-notification', [BroadcastNotificationController::class, 'broadcast']);
Route::get('broadcast-logs',[BroadcastNotificationController::class,'broadcastLogs']);
Route::patch('extend-subscription',[AdminApproveController::class,'extendSubscription']);

// Dashboard inbox: new hospital requests, requests with no free bed, …
Route::prefix('notifications')->group(function () {
    Route::get('/', [AdminNotificationController::class, 'index']);
    Route::get('/unread-count', [AdminNotificationController::class, 'unreadCount']);
    Route::patch('/read-all', [AdminNotificationController::class, 'markAllRead']);
    Route::patch('/{id}/read', [AdminNotificationController::class, 'markRead'])->whereNumber('id');
});

// Detailed per-account report (filter by province etc.), json or csv
Route::get('reports/providers', [AdminReportController::class, 'providers']);

// Platform dues (مستحقات الإدارة) grouped by provider — see ADMIN_DUES_FRONTEND.md
Route::get('dues', [\App\Http\Controllers\Admin\AdminDuesController::class, 'index']);

Route::prefix('service')->group(function () {
    Route::post('/', [ServiceController::class, 'create'])->name('service.create');
    Route::get('/hospitals', [ServiceController::class, 'hospitalsServices']);
    Route::get('/nurses', [ServiceController::class, 'nurseServices']);
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
    Route::get('/nearest', [DoctorController::class, 'getNearestDoctors']);
    Route::get('/{id}', [DoctorStatisticsController::class, 'doctor']);
    Route::get('/{id}/reservations', [DoctorStatisticsController::class, 'doctorReservations']);
    Route::get('/{id}/statistics', [ProviderStatisticsController::class, 'adminDoctor'])->whereNumber('id');
    Route::post('/reserve',[AdminReservationController::class,'doctorReservation']);
    Route::delete('/{id}', [AdminAccountController::class, 'destroyDoctor'])->whereNumber('id');
    Route::patch('/{id}/restore', [AdminAccountController::class, 'restoreDoctor'])->whereNumber('id');

});
Route::prefix('hospital')->group(function () {
    Route::get('/all', [HospitalStatisticsController::class, 'hospitals']);
    Route::get('/search-by-province-service', [HospitalStatisticsController::class, 'searchByProvinceAndService']);
    Route::get('/nearest', [HospitalController::class, 'getNearestHospitals']);
    Route::get('/{id}', [HospitalStatisticsController::class, 'hospital']);
    Route::get('/{id}/reservations', [HospitalStatisticsController::class, 'hospitalReservations']);
    Route::post('/reserve',[AdminReservationController::class,'hospitalReservation']);
    Route::delete('/{id}', [ManageHospitalsAccountsController::class, 'destroyHospitalAccount']);
    Route::get('/{id}/statistics', [ProviderStatisticsController::class, 'adminHospital'])->whereNumber('id');
    Route::patch('/{id}/restore', [ManageHospitalsAccountsController::class, 'restoreHospitalAccount']);
});
Route::prefix('nurse')->group(function () {
    Route::get('/{id}/license', [NurseStatisticsController::class, 'getNurseLicense']);
    Route::get('/all', [NurseStatisticsController::class, 'nurses']);
    // Search nurse services (which nurse offers what) — for booking on behalf of a patient
    Route::get('/services/search', [NurseStatisticsController::class, 'searchServices']);
    Route::get('/nearest', [NurseController::class, 'getNearestNursesAdmin']);
    Route::get('/{id}', [NurseStatisticsController::class, 'nurse']);
    Route::get('/{id}/reservations', [NurseStatisticsController::class, 'nurseReservations']);
    Route::post('/reserve',[AdminReservationController::class,'nurseReservation']);
    Route::get('/{id}/statistics', [ProviderStatisticsController::class, 'adminNurse'])->whereNumber('id');
    Route::delete('/{id}', [AdminAccountController::class, 'destroyNurse'])->whereNumber('id');
    Route::patch('/{id}/restore', [AdminAccountController::class, 'restoreNurse'])->whereNumber('id');
    Route::prefix('services/requests')->group(function (){
        Route::get('',[NurseServiceRequestController::class,'index']);
        Route::patch('approve/{id}',[NurseServiceRequestController::class,'approve']);
        Route::patch('reject/{id}',[NurseServiceRequestController::class,'reject']);
        Route::get('certificate/{id}',[NurseServiceRequestController::class,'showCertificate']);
    });

});
Route::prefix('user')->group(function () {
    Route::get('/all', [UserStatisticsController::class, 'users']);
    Route::get('/{id}/reservations', [UserStatisticsController::class, 'userReservations']);
    Route::delete('/{id}', [AdminAccountController::class, 'destroyUser'])->whereNumber('id');
    Route::patch('/{id}/restore', [AdminAccountController::class, 'restoreUser'])->whereNumber('id');
});
Route::prefix('provinces')->group(function () {
    Route::get('/', [ProvinceController::class, 'index']);
    Route::patch('/', [ProvinceController::class, 'update']);
    Route::post('/', [ProvinceController::class, 'store']);
    Route::delete('/{id}', [ProvinceController::class, 'destroy']);
});

Route::prefix('contact-info')->group(function () {
    Route::get('/', [ContactInfoController::class, 'index']);
    Route::post('/', [ContactInfoController::class, 'store']);
    Route::patch('/{id}', [ContactInfoController::class, 'update']);
    Route::delete('/{id}', [ContactInfoController::class, 'destroy']);
});
Route::prefix('site-content')->group(function () {
    Route::get('/', [SiteContentController::class, 'adminIndex']);
    Route::patch('/reorder', [SiteContentController::class, 'reorder']);
    Route::get('/{key}', [SiteContentController::class, 'show']);
    Route::post('/', [SiteContentController::class, 'storeOrUpdate']);
    Route::delete('/{key}', [SiteContentController::class, 'destroy']);
});

Route::prefix('legal')->group(function () {
    Route::get('/', [LegalDocumentController::class, 'adminIndex']);
    Route::get('/{type}', [LegalDocumentController::class, 'adminShow']);
    Route::post('/{type}', [LegalDocumentController::class, 'update']);
});

Route::get('entity-rates', [\App\Http\Controllers\RatingController::class, 'entityRatings']);
