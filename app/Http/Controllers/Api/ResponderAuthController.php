<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationOtp;
use App\Models\Responder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ResponderAuthController extends Controller
{
    // Covers filling the OTP screen AND coming back to confirm.
    protected const PENDING_TTL_MINUTES = 30;

    protected function pendingKey(string $email): string
    {
        return 'responder_pending_registration:' . strtolower($email);
    }

    /**
     * Step 1 — validates the form and sends the OTP. Does NOT create the
     * responder row. The submitted data is cached under the email so
     * confirmRegistration() can create the real row once verified. This
     * is intentional: an abandoned/unverified registration should never
     * occupy the unique badge_number/email/mobile slots.
     *
     * The badge/ID photo can't be cached like the rest of the form data
     * (Cache::put() serializes to a string store, not raw file bytes), so
     * it's saved to disk right away and only the PATH is carried through
     * the pending-registration cache entry. confirmRegistration() attaches
     * that path to the row once it actually gets created.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name'   => ['required', 'string', 'max:255'],
            'middle_name'  => ['nullable', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'suffix'       => ['nullable', 'string', 'max:20'],
            'badge_number' => ['required', 'string', 'unique:responders,badge_number'],
            'agency'       => ['required', 'in:PNP,BFP,SARS,HCU,MSWD'],
            'unit_station' => ['nullable', 'string'],
            'mobile'       => ['nullable', 'string', 'unique:responders,mobile'],
            'email'        => ['required', 'email', 'unique:responders,email'],
            'password'     => ['required', 'string', 'min:6'],
            // Badge/ID photo — reviewed by MDRRMO admin on the Responder
            // Verification page before the account shows as verified.
            // Doesn't block login (matches citizen behavior), it's a
            // trust signal only.
            'valid_id'     => ['required', 'file', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $fullName = collect([
            $request->first_name,
            $request->middle_name,
            $request->last_name,
            $request->suffix,
        ])->filter(fn ($part) => filled($part))->implode(' ');

        $otp = (string) random_int(100000, 999999);
        $email = $request->email;
        $key = $this->pendingKey($email);

        // If this email already has a pending registration (e.g. they
        // backed out and are retrying), clean up its orphaned upload
        // before we save the new one — otherwise every retry leaves a
        // stray file on disk with nothing ever pointing back to it.
        $existingPending = Cache::get($key);
        if ($existingPending && ! empty($existingPending['data']['valid_id_path'])) {
            Storage::disk('public')->delete($existingPending['data']['valid_id_path']);
        }

        $validIdPath = $request->file('valid_id')->store('responder_ids', 'public');

        // Password is cached in PLAIN TEXT here, not hashed. Responder's
        // 'hashed' cast would otherwise re-hash an already-hashed value
        // when confirmRegistration() calls create(), breaking login.
        // Cache entry is short-lived (30 min) and never touches the DB,
        // so this trade-off is acceptable for this deployment.
        Cache::put($key, [
            'data' => [
                'first_name'     => $request->first_name,
                'middle_name'    => $request->middle_name,
                'last_name'      => $request->last_name,
                'suffix'         => $request->suffix,
                'full_name'      => $fullName,
                'badge_number'   => $request->badge_number,
                'agency'         => $request->agency,
                'unit_station'   => $request->unit_station,
                'mobile'         => $request->mobile,
                'email'          => $email,
                'plain_password' => $request->password,
                'valid_id_path'  => $validIdPath,
            ],
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(10)->timestamp,
            'verified'       => false,
        ], now()->addMinutes(self::PENDING_TTL_MINUTES));

        try {
            Mail::to($email)->send(new EmailVerificationOtp($otp, $fullName));
        } catch (\Throwable $e) {
            Log::error('Responder registration OTP email failed to send', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Could not send the verification code right now. Please try again.'], 500);
        }

        return response()->json([
            'message' => 'Verification code sent. Please check your email.',
            'email'   => $email,
        ]);
    }

    /**
     * Step 2 — verifies the OTP against the pending cache entry only.
     * Still does NOT create the responder row — just flags it verified
     * so confirmRegistration() will accept it.
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $key = $this->pendingKey($request->email);
        $pending = Cache::get($key);

        if (! $pending) {
            return response()->json(['message' => 'No pending registration found for this email. Please register again.'], 422);
        }

        if ($pending['otp'] !== $request->otp) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        if (now()->timestamp > $pending['otp_expires_at']) {
            return response()->json(['message' => 'This code has expired. Please request a new one.'], 422);
        }

        $pending['verified'] = true;
        Cache::put($key, $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

        return response()->json([
            'message'   => 'Email verified',
            'responder' => collect($pending['data'])->except('plain_password'),
        ]);
    }

    public function resendVerificationOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $key = $this->pendingKey($request->email);
        $pending = Cache::get($key);

        if (! $pending) {
            return response()->json(['message' => 'No pending registration found for this email. Please register again.'], 422);
        }

        $otp = (string) random_int(100000, 999999);
        $pending['otp'] = $otp;
        $pending['otp_expires_at'] = now()->addMinutes(10)->timestamp;
        Cache::put($key, $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

        try {
            Mail::to($request->email)->send(new EmailVerificationOtp($otp, $pending['data']['full_name']));
        } catch (\Throwable $e) {
            Log::error('Responder resend verification email failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Could not send the code right now. Please try again in a moment.'], 500);
        }

        return response()->json(['message' => 'A new verification code has been sent.']);
    }

    /**
     * Step 3 — the person is back on the registration screen reviewing
     * their details. This is the ONLY place a responder row gets
     * created. Re-checks uniqueness in case something claimed the
     * badge/email/mobile during the verification window.
     */
    public function confirmRegistration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $key = $this->pendingKey($request->email);
        $pending = Cache::get($key);

        if (! $pending) {
            return response()->json(['message' => 'No pending registration found for this email. Please register again.'], 422);
        }

        if (empty($pending['verified'])) {
            return response()->json(['message' => 'Please verify your email before confirming.'], 422);
        }

        $data = $pending['data'];

        $conflict = Validator::make($data, [
            'badge_number' => ['unique:responders,badge_number'],
            'email'        => ['unique:responders,email'],
            'mobile'       => ['nullable', 'unique:responders,mobile'],
        ]);

        if ($conflict->fails()) {
            // The upload was already saved to disk in step 1 — since this
            // pending registration is being abandoned (conflict means it
            // can never be confirmed as-is), clean it up rather than
            // leaving an orphaned file behind.
            if (! empty($data['valid_id_path'])) {
                Storage::disk('public')->delete($data['valid_id_path']);
            }

            Cache::forget($key);
            return response()->json([
                'message' => 'One of these details was taken while you were verifying your email. Please register again.',
                'errors'  => $conflict->errors(),
            ], 422);
        }

        $responder = Responder::create([
            'first_name'        => $data['first_name'],
            'middle_name'       => $data['middle_name'],
            'last_name'         => $data['last_name'],
            'suffix'            => $data['suffix'],
            'full_name'         => $data['full_name'],
            'badge_number'      => $data['badge_number'],
            'agency'            => $data['agency'],
            'unit_station'      => $data['unit_station'],
            'mobile'            => $data['mobile'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['plain_password']),
            'valid_id_path'     => $data['valid_id_path'] ?? null,
            'email_verified_at' => now(),
            // verification_status defaults to 'pending' at the DB level.
        ]);

        Cache::forget($key);

        // No token — the person logs in manually from here.
        return response()->json([
            'message'   => 'Account created. Please log in.',
            'responder' => $responder,
        ], 201);
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

        $responder = Responder::where('email', $request->email)->first();

        if (! $responder || ! Hash::check($request->password, $responder->password)) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        if ($responder->status !== 'active') {
            return response()->json(['message' => 'This account has been deactivated. Contact your agency admin.'], 403);
        }

        $token = $responder->createToken('responder-app')->plainTextToken;

        return response()->json([
            'message'   => 'Login successful',
            'responder' => $responder,
            'token'     => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}