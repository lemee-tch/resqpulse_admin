<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Mail\PasswordResetOtp;
use App\Mail\EmailVerificationOtp;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name'   => ['required', 'string', 'max:255'],
            'middle_name'  => ['nullable', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'suffix'       => ['nullable', 'string', 'max:20'],
            'mobile'       => ['required', 'string', 'unique:citizens,mobile'],
            'email'        => ['required', 'email', 'unique:citizens,email'],
            'password'     => ['required', 'string', 'min:6'],
            'municipality' => ['required', 'string'],
            'barangay'     => ['required', 'string'],
            'street'       => ['nullable', 'string'],
            'zone'         => ['nullable', 'string'],
            'valid_id'     => ['required', 'file', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $validIdPath = $request->hasFile('valid_id')
            ? $request->file('valid_id')->store('citizen_ids', 'public')
            : null;

        $fullName = collect([
            $request->first_name,
            $request->middle_name,
            $request->last_name,
            $request->suffix,
        ])->filter(fn ($part) => filled($part))->implode(' ');

        $otp = (string) random_int(100000, 999999);

        $citizen = Citizen::create([
            'first_name'    => $request->first_name,
            'middle_name'   => $request->middle_name,
            'last_name'     => $request->last_name,
            'suffix'        => $request->suffix,
            'full_name'     => $fullName,
            'mobile'        => $request->mobile,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'municipality'  => $request->municipality,
            'barangay'      => $request->barangay,
            'street'        => $request->street,
            'zone'          => $request->zone,
            'valid_id_path' => $validIdPath,
            'verification_otp'            => $otp,
            'verification_otp_expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($citizen->email)->send(new EmailVerificationOtp($otp, $citizen->full_name));

        // No token yet — the citizen must verify their email before they can log in.
        return response()->json([
            'message' => 'Registration successful. Please check your email for a verification code.',
            'citizen' => $citizen,
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $citizen = Citizen::where('email', $request->email)
            ->where('verification_otp', $request->otp)
            ->first();

        if (! $citizen) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        if ($citizen->verification_otp_expires_at === null || now()->greaterThan($citizen->verification_otp_expires_at)) {
            return response()->json(['message' => 'This code has expired. Please request a new one.'], 422);
        }

        $citizen->update([
            'email_verified_at'           => now(),
            'verification_otp'            => null,
            'verification_otp_expires_at' => null,
        ]);

        $token = $citizen->createToken('mobile-app')->plainTextToken;

        return response()->json(['message' => 'Email verified', 'citizen' => $citizen, 'token' => $token]);
    }

    public function resendVerificationOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $citizen = Citizen::where('email', $request->email)->first();

        if (! $citizen) {
            return response()->json(['message' => 'If that email is registered, a new code has been sent.']);
        }

        if ($citizen->email_verified_at !== null) {
            return response()->json(['message' => 'This email is already verified.'], 422);
        }

        $otp = (string) random_int(100000, 999999);
        $citizen->update([
            'verification_otp'            => $otp,
            'verification_otp_expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($citizen->email)->send(new EmailVerificationOtp($otp, $citizen->full_name));

        return response()->json(['message' => 'A new verification code has been sent.']);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $citizen = Citizen::where('email', $request->email)->first();

        if (! $citizen || ! $citizen->password || ! Hash::check($request->password, $citizen->password)) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        if ($citizen->email_verified_at === null) {
            return response()->json([
                'message'            => 'Please verify your email before logging in.',
                'needs_verification' => true,
                'email'              => $citizen->email,
            ], 403);
        }

        $token = $citizen->createToken('mobile-app')->plainTextToken;

        // Resident activity trail — shows up in the admin Audit Log
        // page (filterable by action=login). auth()->id()/auth()->user()
        // inside AuditLogService refer to the ADMIN web guard, which is
        // never authenticated here (this is the citizen mobile API), so
        // user_id/user_name stay null on this row — the actual actor is
        // captured via $auditable (this Citizen) and named directly in
        // the description, same convention already used for
        // approve/reject in Admin\CitizenVerificationController.
        AuditLogService::log('login', "Citizen {$citizen->full_name} logged in.", $citizen);

        return response()->json(['message' => 'Login successful', 'citizen' => $citizen, 'token' => $token]);
    }


    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $citizen = Citizen::where('email', $request->email)->first();

        // Don't reveal whether the email exists — same response either way
        if (! $citizen) {
            return response()->json(['message' => 'If that email is registered, a code has been sent.']);
        }

        $otp = (string) random_int(100000, 999999);

        $citizen->update([
            'reset_otp' => $otp,
            'reset_otp_expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($citizen->email)->send(new PasswordResetOtp($otp, $citizen->full_name));

        return response()->json(['message' => 'If that email is registered, a code has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'otp'      => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $citizen = Citizen::where('email', $request->email)
            ->where('reset_otp', $request->otp)
            ->first();

        if (! $citizen) {
            return response()->json(['message' => 'Invalid code.'], 422);
        }

        if ($citizen->reset_otp_expires_at === null || now()->greaterThan($citizen->reset_otp_expires_at)) {
            return response()->json(['message' => 'This code has expired. Please request a new one.'], 422);
        }

        $citizen->update([
            'password' => Hash::make($request->password),
            'reset_otp' => null,
            'reset_otp_expires_at' => null,
        ]);

        return response()->json(['message' => 'Password reset successful. Please log in.']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}