<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AlertController as ApiAlertController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\EvacuationCenterController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/evacuation-centers', [EvacuationCenterController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/alerts', [ApiAlertController::class, 'index']);
    Route::post('/fcm-token', [ApiAlertController::class, 'updateToken']);
    Route::post('/incidents', [IncidentController::class, 'store']);
    Route::get('/incidents/mine', [IncidentController::class, 'mine']);
    
});

