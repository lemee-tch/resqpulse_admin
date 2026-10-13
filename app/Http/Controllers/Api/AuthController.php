<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Mail\PasswordResetOtp;
use App\Mail\EmailVerificationOtp;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * Creates the account and emails the 6-digit code.
     *
     * Sending the form again also works when a sign-up was started but
     * never finished (the code was never entered) — e.g. the resident
     * typed a wrong email. The new sign-up replaces the unfinished one;
     * before, the mobile number counted as "already taken" and the
     * resident was stuck. See unfinishedSignups() for when that's allowed.
     *
     * Optional fields, sent by the app's Create Account screen when the
     * resident edits their details after "Send Code":
     *   previous_email, previous_password — what that "Send Code" used.
     */
    public function register(Request $request)
    {
        [$replaceable, $waiting] = $this->unfinishedSignups($request);
        $replaceIds = $replaceable->pluck('id')->all();

        $validator = Validator::make($request->all(), [
            'first_name'   => ['required', 'string', 'max:255'],
            'middle_name'  => ['nullable', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'suffix'       => ['nullable', 'string', 'max:20'],
            // Unique, except for unfinished sign-ups this one replaces.
            'mobile'       => ['required', 'string', Rule::unique('citizens', 'mobile')->whereNotIn('id', $replaceIds)],
            'email'        => ['required', 'email', Rule::unique('citizens', 'email')->whereNotIn('id', $replaceIds)],
            // Was min:6 with no complexity requirement — now at least 8
            // characters with a mix of upper/lower case and a number.
            // Illuminate\Validation\Rules\Password ships with Laravel,
            // no new package needed.
            'password'     => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'municipality' => ['required', 'string'],
            'barangay'     => ['required', 'string'],
            'street'       => ['nullable', 'string'],
            'zone'         => ['nullable', 'string'],
            'valid_id'     => ['required', 'file', 'image', 'max:5120'],
        ], [
            'mobile.unique' => $this->takenMessage($waiting, 'mobile', $this->inputString($request, 'mobile')),
            'email.unique'  => $this->takenMessage($waiting, 'email', $this->inputString($request, 'email')),
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Stored on the PRIVATE 'local' disk (storage/app/private, see
        // config/filesystems.php) instead of 'public' — a government-ID
        // photo has no business being reachable by anyone with the URL
        // via the public storage symlink. It's now only served through
        // the auth-gated route CitizenVerificationController::idPhoto()
        // (admin panel only). Existing files already under
        // storage/app/public/citizen_ids need to be moved by hand to
        // storage/app/private/citizen_ids on the server — there's no
        // CLI access to script this migration.
        $validIdPath = $request->hasFile('valid_id')
            ? $request->file('valid_id')->store('citizen_ids', 'local')
            : null;

        $fullName = collect([
            $request->first_name,
            $request->middle_name,
            $request->last_name,
            $request->suffix,
        ])->filter(fn ($part) => filled($part))->implode(' ');

        $otp = (string) random_int(100000, 999999);

        try {
            // Removing the unfinished sign-up(s) and saving the new one
            // happen together: if saving fails, the old one is kept.
            $citizen = DB::transaction(function () use ($request, $replaceable, $fullName, $validIdPath, $otp) {
                foreach ($replaceable as $old) {
                    $old->delete();
                }

                return Citizen::create([
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
            });
        } catch (\Throwable $e) {
            $this->deleteIdPhoto($validIdPath);
            throw $e;
        }

        // The replaced sign-ups' ID photos aren't needed anymore.
        foreach ($replaceable as $old) {
            $this->deleteIdPhoto($old->valid_id_path);
        }

        Mail::to($citizen->email)->send(new EmailVerificationOtp($otp, $citizen->full_name));

        // Resident activity trail — same convention as login()/
        // updateProfile() below: no admin session here, so user_id/
        // user_name stay null and the actor is named directly via
        // $auditable (this Citizen). Also what the Residents Log page
        // reads to show "who registered" (Admin\ResidentsLogController).
        if ($replaceable->isEmpty()) {
            AuditLogService::log('created', "Citizen {$citizen->full_name} registered.", $citizen);
        } else {
            // Keeps a trace of what was corrected, e.g. a mistyped email.
            $before  = $replaceable->first()->only(['email', 'mobile']);
            $after   = $citizen->only(['email', 'mobile']);
            $changed = array_diff_assoc($before, $after);

            AuditLogService::log(
                'created',
                "Citizen {$citizen->full_name} registered again, replacing a sign-up that was never verified.",
                $citizen,
                $changed ?: null,
                $changed ? array_intersect_key($after, $changed) : null
            );
        }

        // No token yet — the citizen must verify their email before they can log in.
        return response()->json([
            'message' => $replaceable->isEmpty()
                ? 'Registration successful. Please check your email for a verification code.'
                : 'Your sign-up was updated. Please check your email for a new verification code.',
            'citizen' => $citizen,
        ], 201);
    }

    /**
     * Unfinished sign-ups (email never verified) that this request runs
     * into, split into [the ones it may replace, the ones it may not].
     *
     * An unfinished sign-up may be replaced when:
     *  - it's the one the Create Account screen made and the resident is
     *    now correcting (previous_email + previous_password), so a wrong
     *    email, mobile number or password can be fixed;
     *  - the same password is used — the resident closed the app and
     *    signed up again;
     *  - it has the same email or mobile number and its code has expired —
     *    nobody is entering a code for it anymore. A resident who forgot
     *    the password they used can start over once 10 minutes have passed.
     *
     * Verified accounts are never touched. An unfinished sign-up whose
     * code is still valid can't be replaced by someone with a different
     * password, so nobody can take over a sign-up while it's in progress.
     */
    private function unfinishedSignups(Request $request): array
    {
        $email         = $this->inputString($request, 'email');
        $mobile        = $this->inputString($request, 'mobile');
        $previousEmail = $this->inputString($request, 'previous_email');

        if ($email === '' && $mobile === '' && $previousEmail === '') {
            return [collect(), collect()];
        }

        $passwords = array_values(array_filter(
            [$request->input('password'), $request->input('previous_password')],
            fn ($password) => is_string($password) && $password !== ''
        ));

        $unfinished = Citizen::whereNull('email_verified_at')
            ->where(function ($query) use ($email, $mobile, $previousEmail) {
                if ($email !== '') {
                    $query->orWhere('email', $email);
                }
                if ($mobile !== '') {
                    $query->orWhere('mobile', $mobile);
                }
                if ($previousEmail !== '') {
                    $query->orWhere('email', $previousEmail);
                }
            })
            ->get();

        [$replaceable, $kept] = $unfinished->partition(function (Citizen $citizen) use ($email, $mobile, $passwords) {
            $clashes = ($email !== '' && strcasecmp((string) $citizen->email, $email) === 0)
                || ($mobile !== '' && $citizen->mobile === $mobile);

            if ($clashes && $this->codeExpired($citizen)) {
                return true;
            }

            foreach ($passwords as $password) {
                if ($citizen->password && Hash::check($password, $citizen->password)) {
                    return true;
                }
            }

            return false;
        });

        return [$replaceable->values(), $kept->values()];
    }

    /**
     * Message for an email / mobile number that's already used. When it's
     * an unfinished sign-up that is still waiting for its code, tells the
     * resident how to get past it instead of only "already taken".
     */
    private function takenMessage(Collection $unfinished, string $field, string $value): string
    {
        $waiting = $unfinished->first(
            fn (Citizen $citizen) => strcasecmp((string) $citizen->{$field}, $value) === 0
        );

        if ($waiting) {
            $minutes = $this->minutesLeft($waiting->verification_otp_expires_at);
            $label   = $field === 'email' ? 'email' : 'mobile number';

            return "This {$label} is in a sign-up that is still waiting for its code. "
                . 'Use the same password as before to continue it, or try again in '
                . $minutes . ($minutes === 1 ? ' minute.' : ' minutes.');
        }

        return $field === 'email'
            ? 'This email already has an account. Log in instead, or tap "Forgot your password?" on the login screen.'
            : 'This mobile number already has an account.';
    }

    private function codeExpired(Citizen $citizen): bool
    {
        $expiresAt = $citizen->verification_otp_expires_at;

        return $expiresAt === null || now()->greaterThan($expiresAt);
    }

    /** Whole minutes until the code expires, at least 1. */
    private function minutesLeft($expiresAt): int
    {
        if ($expiresAt === null) {
            return 1;
        }

        $seconds = Carbon::parse($expiresAt)->getTimestamp() - now()->getTimestamp();

        return max(1, (int) ceil($seconds / 60));
    }

    private function inputString(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Deletes an ID photo. New ones are on the private 'local' disk; old
     * ones may still be on 'public' (see the note in register()).
     */
    private function deleteIdPhoto(?string $path): void
    {
        if (! $path) {
            return;
        }

        foreach (['local', 'public'] as $disk) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable) {
                // Missing file or disk — nothing to clean up.
            }
        }
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

    /**
     * Lets a logged-in citizen update their own name, mobile number and
     * address fields — the counterpart to register() above, but for an
     * EXISTING account instead of creating one. Deliberately excludes
     * email (tied to login + verification_status) and municipality
     * (fixed to Rosales, same as register()); password changes stay on
     * the separate forgot/reset-password OTP flow, not this endpoint.
     *
     * Resident activity trail — shows up in the admin Audit Log page
     * (filterable by action=updated), same convention as login()/
     * store()/sos(): user_id/user_name stay null (no admin session
     * here), the actor is captured via $auditable (this Citizen), and
     * old/new only carry the fields that actually changed.
     */
    public function updateProfile(Request $request)
    {
        $citizen = $request->user();

        if (! $citizen instanceof Citizen) {
            return response()->json(['message' => 'Not a citizen account.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name'  => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name'   => ['required', 'string', 'max:255'],
            'suffix'      => ['nullable', 'string', 'max:20'],
            'mobile'      => [
                'required',
                'string',
                Rule::unique('citizens', 'mobile')->ignore($citizen->id),
            ],
            'barangay'    => ['required', 'string'],
            'street'      => ['nullable', 'string'],
            'zone'        => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $trackedFields = ['first_name', 'middle_name', 'last_name', 'suffix', 'mobile', 'barangay', 'street', 'zone'];
        $before = $citizen->only($trackedFields);

        $fullName = collect([
            $request->first_name,
            $request->middle_name,
            $request->last_name,
            $request->suffix,
        ])->filter(fn ($part) => filled($part))->implode(' ');

        $citizen->update([
            'first_name'  => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name'   => $request->last_name,
            'suffix'      => $request->suffix,
            'full_name'   => $fullName,
            'mobile'      => $request->mobile,
            'barangay'    => $request->barangay,
            'street'      => $request->street,
            'zone'        => $request->zone,
        ]);

        $after = $citizen->only($trackedFields);

        // Only log (and only report) fields that actually changed —
        // resubmitting the form with nothing edited shouldn't create a
        // noise row in the Audit Log.
        $changedBefore = array_diff_assoc($before, $after);
        if ($changedBefore !== []) {
            $changedAfter = array_intersect_key($after, $changedBefore);
            AuditLogService::log(
                'updated',
                "Citizen {$citizen->full_name} updated their profile.",
                $citizen,
                $changedBefore,
                $changedAfter
            );
        }

        return response()->json(['message' => 'Profile updated.', 'citizen' => $citizen]);
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
            // Same strengthened rule as register() — kept identical so a
            // citizen can't sidestep the stronger policy just by going
            // through the reset flow instead of signing up fresh.
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
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