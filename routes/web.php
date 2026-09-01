<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\Admin\CitizenVerificationController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\EvacuationCenterController;
use App\Http\Controllers\MapViewController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ResponderAccountController;

Route::middleware('auth')->group(function () {
    Route::get('/citizen-verification', [CitizenVerificationController::class, 'index'])->name('citizen-verification');
    Route::post('/citizen-verification/{citizen}/approve', [CitizenVerificationController::class, 'approve'])->name('citizen-verification.approve');
    Route::post('/citizen-verification/{citizen}/reject', [CitizenVerificationController::class, 'reject'])->name('citizen-verification.reject');

    // Responder accounts are pre-built, one per agency — this page
    // replaces the old individual-responder verification workflow
    // (approve/reject an uploaded ID) since there's no self-registration
    // to verify anymore. Manage credentials/contact details here instead.
    Route::get('/responder-accounts', [ResponderAccountController::class, 'index'])->name('responder-accounts');
    Route::patch('/responder-accounts/{responder}', [ResponderAccountController::class, 'update'])->name('responder-accounts.update');
    Route::post('/responder-accounts/{responder}/reset-password', [ResponderAccountController::class, 'resetPassword'])->name('responder-accounts.reset-password');
    Route::post('/responder-accounts/{responder}/toggle-status', [ResponderAccountController::class, 'toggleStatus'])->name('responder-accounts.toggle-status');
});

Route::get('/', fn() => redirect()->route('login'));
Route::get('/login',     [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');

//fogot password
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetOtp'])->name('password.email');
Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard',  [AuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/incident',  [IncidentController::class, 'index'])->name('incident');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incident.detail');
    Route::get('/sos-alerts', [IncidentController::class, 'sosIndex'])->name('sos-alerts');
    Route::get('/reports-analytics', [AuthController::class, 'reportsAnalytics'])->name('reports-analytics');
    Route::get('/evacuation', [EvacuationCenterController::class, 'index'])->name('evacuation');
    Route::post('/evacuation', [EvacuationCenterController::class, 'store'])->name('evacuation.store');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts');
    Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
    Route::get('/mapview', [MapViewController::class, 'index'])->name('mapview');
    Route::post('/mapview/refresh-boundaries', [MapViewController::class, 'refreshBoundaries'])->name('mapview.refresh-boundaries');

    Route::patch('/incidents/{incident}/priority', [IncidentController::class, 'updatePriority'])->name('incident.priority');
    Route::patch('/incidents/{incident}/status', [IncidentController::class, 'updateStatus'])->name('incident.status');
    Route::patch('/incidents/{incident}/note', [IncidentController::class, 'updateNote'])->name('incident.note');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incident.detail');

    Route::get('/sos-alerts/latest', [IncidentController::class, 'latestSos'])->name('sos-alerts.latest');
    Route::get('/audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-log');

});