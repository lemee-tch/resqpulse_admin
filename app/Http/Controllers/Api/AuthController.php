<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Mail\PasswordResetOtp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name'    => ['required', 'string', 'max:255'],
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

        $citizen = Citizen::create([
            'full_name'     => $request->full_name,
            'mobile'        => $request->mobile,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'municipality'  => $request->municipality,
            'barangay'      => $request->barangay,
            'street'        => $request->street,
            'zone'          => $request->zone,
            'valid_id_path' => $validIdPath,
        ]);

        $token = $citizen->createToken('mobile-app')->plainTextToken;

        return response()->json(['message' => 'Registration successful', 'citizen' => $citizen, 'token' => $token], 201);
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

        $token = $citizen->createToken('mobile-app')->plainTextToken;

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