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

// ── Responder accounts are pre-built, one shared login per agency
// (PNP, BFP, SARS, HCU, MSWD) — created by ResponderSeeder and managed
// from the admin panel's "Responder Accounts" page. There is no
// self-service registration anymore, so those routes are intentionally
// NOT exposed here. Login is the only public responder entry point;
// createToken() inside it never revokes prior tokens, so any number of
// staff within an agency stay signed in on their own devices at once.
Route::post('/responder/login', [ResponderAuthController::class, 'login']);

// ── Guest-capable incident/SOS reporting ────────────────────────────
// Deliberately OUTSIDE auth:sanctum: a citizen app in guest mode has no
// token to send. IncidentController::resolveOptionalCitizen() reads the
// bearer token manually when one IS present (a logged-in citizen), so
// both flows share this single endpoint. Guest submissions are flagged
// needs_review=true and held from responders until an admin approves
// them from the admin panel — see Admin\IncidentController::approve().
Route::post('/incidents', [IncidentController::class, 'store']);
Route::post('/incidents/sos', [IncidentController::class, 'sos']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/alerts', [ApiAlertController::class, 'index']);
    Route::post('/fcm-token', [ApiAlertController::class, 'updateToken']);
    Route::get('/incidents/mine', [IncidentController::class, 'mine']);
    Route::get('/responder/me', [ResponderAuthController::class, 'me']);
    Route::post('/responder/logout', [ResponderAuthController::class, 'logout']);
    Route::get('/responder/incidents', [IncidentController::class, 'assignedToResponder']);

    Route::post('/evacuation-centers', [EvacuationCenterController::class, 'store']);
    Route::patch('/evacuation-centers/{center}/status', [EvacuationCenterController::class, 'updateStatus']);
    Route::post('/evacuation-centers/{center}/evacuees', [EvacuationCenterController::class, 'storeEvacuee']);

    Route::post('/responder/incidents/{incident}/accept', [IncidentController::class, 'accept']);
    Route::post('/responder/incidents/{incident}/decline', [IncidentController::class, 'decline']);
});