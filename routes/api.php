<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AlertController as ApiAlertController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\EvacuationCenterController;
use App\Http\Controllers\Api\ResponderAuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification-otp', [AuthController::class, 'resendVerificationOtp']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/evacuation-centers', [EvacuationCenterController::class, 'index']);
Route::post('/responder/register', [ResponderAuthController::class, 'register']);
Route::post('/responder/login', [ResponderAuthController::class, 'login']);
Route::post('/responder/verify-email', [ResponderAuthController::class, 'verifyEmail']);
Route::post('/responder/resend-verification-otp', [ResponderAuthController::class, 'resendVerificationOtp']);
Route::post('/responder/confirm-registration', [ResponderAuthController::class, 'confirmRegistration']);
Route::post('/responder/forgot-password', [ResponderAuthController::class, 'forgotPassword']);
Route::post('/responder/reset-password', [ResponderAuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/alerts', [ApiAlertController::class, 'index']);
    Route::post('/fcm-token', [ApiAlertController::class, 'updateToken']);
    Route::post('/incidents', [IncidentController::class, 'store']);
    Route::post('/incidents/sos', [IncidentController::class, 'sos']);
    Route::get('/incidents/mine', [IncidentController::class, 'mine']);
    Route::get('/responder/me', [ResponderAuthController::class, 'me']);
    Route::post('/responder/logout', [ResponderAuthController::class, 'logout']); 
    Route::get('/responder/incidents', [IncidentController::class, 'assignedToResponder']);

    Route::post('/evacuation-centers', [EvacuationCenterController::class, 'store']);
});