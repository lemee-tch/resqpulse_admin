<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\Admin\CitizenVerificationController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\EvacuationCenterController;
use App\Http\Controllers\MapViewController;
use App\Http\Controllers\Admin\UserController;

Route::middleware('auth')->group(function () {
    Route::get('/citizen-verification', [CitizenVerificationController::class, 'index'])->name('citizen-verification');
    Route::post('/citizen-verification/{citizen}/approve', [CitizenVerificationController::class, 'approve'])->name('citizen-verification.approve');
    Route::post('/citizen-verification/{citizen}/reject', [CitizenVerificationController::class, 'reject'])->name('citizen-verification.reject');
});

Route::get('/', fn() => redirect()->route('login'));
Route::get('/login',     [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard',  [AuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/incident',  [IncidentController::class, 'index'])->name('incident');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incident.detail');    
    Route::get('/reports-analytics', [AuthController::class, 'reportsAnalytics'])->name('reports-analytics');
    Route::get('/users', [AuthController::class, 'users'])->name('users');
    Route::get('/evacuation', [EvacuationCenterController::class, 'index'])->name('evacuation');
    Route::post('/evacuation', [EvacuationCenterController::class, 'store'])->name('evacuation.store');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts');
    Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
    Route::get('/mapview', [MapViewController::class, 'index'])->name('mapview');

    Route::patch('/incidents/{incident}/priority', [IncidentController::class, 'updatePriority'])->name('incident.priority');
    Route::patch('/incidents/{incident}/status', [IncidentController::class, 'updateStatus'])->name('incident.status');
    Route::patch('/incidents/{incident}/note', [IncidentController::class, 'updateNote'])->name('incident.note');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incident.detail');
});