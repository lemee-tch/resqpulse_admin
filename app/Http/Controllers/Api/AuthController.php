<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Services\AuditLogService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Mail\PasswordResetOtp;
use App\Mail\EmailVerificationOtp;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /** Minutes a sign-up code stays valid. */
    private const CODE_MINUTES = 10;

    /** Wrong codes allowed before the resident has to get a new one. */
    private const MAX_WRONG_CODES = 5;

    /**
     * GET /api/verification-options — what Create Account step 3 can
     * offer. The app enables "Mobile Number" only when sms is true, i.e.
     * an SMS gateway is set up in .env (see SmsService).
     */
    public function verificationOptions(SmsService $sms)
    {
        return response()->json([
            'email' => true,
            'sms'   => $sms->isAvailable(),
        ]);
    }

    /**
     * Creates the account and sends the 6-digit code.
     *
     * Two kinds of account, picked on Create Account step 3:
     *   Email          email + mobile number (so MDRRMO can call). Logs in
     *                  with the email; the code goes by email.
     *   Mobile Number  mobile number only, no email (verify_via=sms and no
     *                  email sent). Logs in with the mobile number; the code
     *                  is texted. Needs texting to be on (503 otherwise).
     *
     * App builds from before mobile accounts send verify_via=sms together
     * with an email: that's an email account whose code is texted, and if
     * the text can't be sent the code goes by email instead. A mobile
     * account has no email to fall back to — if its text fails, the reply
     * says code_sent=false and "Resend code" tries again.
     *
     * The reply says where the code went (verify_via) and whether it was
     * sent (code_sent); the app shows the code entry for that.
     *
     * Sending the form again also works when a sign-up was started but
     * never finished (the code was never entered) — e.g. the resident
     * typed a wrong email. The new sign-up replaces the unfinished one;
     * before, the mobile number counted as "already taken" and the
     * resident was stuck. See unfinishedSignups() for when that's allowed.
     *
     * Optional fields, sent by the app's Create Account screen when the
     * resident edits their details after "Send Code":
     *   previous_email, previous_mobile, previous_password — what that
     *   "Send Code" used.
     */
    public function register(Request $request, SmsService $sms)
    {
        $wantsSms      = $request->input('verify_via') === 'sms';
        $mobileAccount = $wantsSms && $this->inputString($request, 'email') === '';

        // A mobile account gets its code only by text.
        if ($mobileAccount && ! $sms->isAvailable()) {
            return response()->json([
                'message' => 'Signing up with a mobile number isn\'t available right now. Please choose Email instead.',
            ], 503);
        }

        [$replaceable, $waiting] = $this->unfinishedSignups($request);
        $replaceIds = $replaceable->pluck('id')->all();

        $useSms = $wantsSms && $sms->isAvailable();

        $validator = Validator::make($request->all(), [
            'first_name'   => ['required', 'string', 'max:255'],
            'middle_name'  => ['nullable', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'suffix'       => ['nullable', 'string', 'max:20'],
            // Unique, except for unfinished sign-ups this one replaces.
            // When the code is texted, it must also be a number we can text.
            'mobile'       => array_values(array_filter([
                'required',
                'string',
                Rule::unique('citizens', 'mobile')->whereNotIn('id', $replaceIds),
                $useSms ? $this->textableNumberRule() : null,
            ])),
            // Not asked for a mobile account (it logs in with the number).
            'email'        => $mobileAccount
                ? ['nullable']
                : ['required', 'email', Rule::unique('citizens', 'email')->whereNotIn('id', $replaceIds)],
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
        $via = $useSms ? 'sms' : 'email';

        try {
            // Removing the unfinished sign-up(s) and saving the new one
            // happen together: if saving fails, the old one is kept.
            $citizen = DB::transaction(function () use ($request, $replaceable, $fullName, $validIdPath, $otp, $via, $mobileAccount) {
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
                    'email'         => $mobileAccount ? null : $request->email,
                    'password'      => Hash::make($request->password),
                    'municipality'  => $request->municipality,
                    'barangay'      => $request->barangay,
                    'street'        => $request->street,
                    'zone'          => $request->zone,
                    'valid_id_path' => $validIdPath,
                    'verification_otp'            => $otp,
                    'verification_otp_expires_at' => now()->addMinutes(self::CODE_MINUTES),
                    'verification_otp_via'        => $via,
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

        $delivery = $this->sendCode($citizen, $otp, $via, $sms, fallBackToEmail: ! $mobileAccount);

        // Where the code really went, and which code (see SmsService).
        $citizen->update([
            'verification_otp'     => $delivery['code'] ?? $otp,
            'verification_otp_via' => $delivery['via'],
        ]);

        if ($delivery['sent']) {
            $this->clearWrongCodes($citizen);
        }

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

        $isNew = $replaceable->isEmpty();

        $message = match (true) {
            ! $delivery['sent'] =>
                'Your account was created, but we couldn\'t send the code right now. Tap "Resend code" to try again.',
            $delivery['via'] === 'sms' => $isNew
                ? 'Registration successful. We texted a verification code to your mobile number.'
                : 'Your sign-up was updated. We texted a new verification code to your mobile number.',
            $wantsSms =>
                'We couldn\'t text your mobile number right now, so we sent the code to your email instead.',
            default => $isNew
                ? 'Registration successful. Please check your email for a verification code.'
                : 'Your sign-up was updated. Please check your email for a new verification code.',
        };

        // No token yet — the citizen must enter the code before they can log in.
        return response()->json([
            'message'    => $message,
            'verify_via' => $delivery['via'],
            'code_sent'  => $delivery['sent'],
            'citizen'    => $citizen,
        ], 201);
    }

    /**
     * Sends $otp by text ($via = 'sms') or email.
     *
     * Returns via (where it went), sent, code (the code that was sent —
     * see SmsService::sendVerificationCode), and reason / retry_after when
     * it wasn't sent.
     *
     * With $fallBackToEmail, a text that can't be sent goes by email
     * instead. Used by register(); a resend doesn't fall back, because the
     * app would then say the code went to the mobile number.
     */
    private function sendCode(Citizen $citizen, string $otp, string $via, SmsService $sms, bool $fallBackToEmail = false): array
    {
        // No email on a mobile account: text only.
        if ($citizen->isMobileAccount()) {
            $via             = 'sms';
            $fallBackToEmail = false;
        }

        if ($via === 'sms') {
            $text = $sms->sendVerificationCode((string) $citizen->mobile, $otp, self::CODE_MINUTES);

            if ($text['sent']) {
                return ['via' => 'sms', 'sent' => true, 'code' => $text['code']];
            }

            if (! $fallBackToEmail) {
                return [
                    'via'         => 'sms',
                    'sent'        => false,
                    'code'        => null,
                    'reason'      => $text['reason'],
                    'retry_after' => $text['retry_after'],
                ];
            }
        }

        try {
            Mail::to($citizen->email)->send(new EmailVerificationOtp($otp, $citizen->full_name));
        } catch (\Throwable $e) {
            // The account is kept; "Resend code" tries again.
            report($e);

            return ['via' => 'email', 'sent' => false, 'code' => null, 'reason' => 'mail_error', 'retry_after' => null];
        }

        return ['via' => 'email', 'sent' => true, 'code' => $otp];
    }

    /** What the app shows when a resend couldn't send the code, and the status. */
    private function notSentReply(array $delivery, Citizen $citizen): array
    {
        $wait = max(1, (int) ($delivery['retry_after'] ?? 60));

        // A mobile account has no email to send the code to instead.
        $hasEmail = ! $citizen->isMobileAccount();
        $orEmail  = $hasEmail ? ', or send the code to your email instead.' : '.';

        return match ($delivery['reason'] ?? null) {
            'too_soon' => [
                "Please wait {$wait} " . ($wait === 1 ? 'second' : 'seconds') . ' before asking for another code.',
                429,
            ],
            'hourly_limit' => [
                'Too many codes were sent to this number. Try again later' . $orEmail,
                429,
            ],
            'invalid_number' => [
                "We can't text {$citizen->mobile}. Check your mobile number" . $orEmail,
                422,
            ],
            'mail_error' => [
                'We couldn\'t send the email right now. Please try again in a minute.',
                503,
            ],
            'gateway_error' => [
                'We couldn\'t send the text message right now. Try again in a minute' . $orEmail,
                503,
            ],
            // unavailable, daily_limit
            default => [
                $hasEmail
                    ? 'Text messages aren\'t available right now. Please send the code to your email instead.'
                    : 'Text messages aren\'t available right now. Please try again later.',
                503,
            ],
        };
    }

    /** A Philippine mobile number the SMS gateway can text. */
    private function textableNumberRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) {
            if (SmsService::normalizeNumber(is_string($value) ? $value : null) === null) {
                $fail('Enter a valid 11-digit mobile number (09XXXXXXXXX).');
            }
        };
    }

    /** Counts wrong sign-up codes per account login (see verifyEmail()). */
    private function wrongCodeKey(string $login): string
    {
        return 'signup-code-wrong:' . sha1($login);
    }

    /** A new code was sent or the right one entered — start counting again. */
    private function clearWrongCodes(Citizen $citizen): void
    {
        foreach ($this->loginsOf($citizen) as $login) {
            RateLimiter::clear($this->wrongCodeKey($login));
        }
    }

    /**
     * Which account the request is about: the "email" sent, or else the
     * "mobile" number (a mobile account has no email).
     */
    private function accountFromRequest(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return $request->filled('email')
            ? Citizen::where('email', $this->inputString($request, 'email'))
            : Citizen::whereMobileNumber($this->inputString($request, 'mobile'));
    }

    /** The email (lower case) or mobile number (639XXXXXXXXX) the request names. */
    private function loginFromRequest(Request $request): string
    {
        $email = $this->inputString($request, 'email');

        if ($email !== '') {
            return strtolower($email);
        }

        return (string) (SmsService::normalizeNumber($this->inputString($request, 'mobile')) ?? '');
    }

    /** The same for an account: its email and its mobile number. */
    private function loginsOf(Citizen $citizen): array
    {
        return array_values(array_filter([
            $citizen->isMobileAccount() ? null : strtolower(trim((string) $citizen->email)),
            SmsService::normalizeNumber((string) $citizen->mobile),
        ]));
    }

    /**
     * Unfinished sign-ups (code never entered, by email or by text) that
     * this request runs into, split into [the ones it may replace, the
     * ones it may not].
     *
     * An unfinished sign-up may be replaced when:
     *  - it's the one the Create Account screen made and the resident is
     *    now correcting (previous_email / previous_mobile +
     *    previous_password), so a wrong email, mobile number or password
     *    can be fixed;
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
        $email          = $this->inputString($request, 'email');
        $mobile         = $this->inputString($request, 'mobile');
        $previousEmail  = $this->inputString($request, 'previous_email');
        $previousMobile = $this->inputString($request, 'previous_mobile');

        if ($email === '' && $mobile === '' && $previousEmail === '' && $previousMobile === '') {
            return [collect(), collect()];
        }

        $passwords = array_values(array_filter(
            [$request->input('password'), $request->input('previous_password')],
            fn ($password) => is_string($password) && $password !== ''
        ));

        $unfinished = Citizen::contactUnverified()
            ->where(function ($query) use ($email, $mobile, $previousEmail, $previousMobile) {
                if ($email !== '') {
                    $query->orWhere('email', $email);
                }
                if ($mobile !== '') {
                    $query->orWhere('mobile', $mobile);
                }
                if ($previousEmail !== '') {
                    $query->orWhere('email', $previousEmail);
                }
                if ($previousMobile !== '') {
                    $query->orWhere('mobile', $previousMobile);
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

    /**
     * Checks the sign-up code — the same call for a code sent by email or
     * by text. The app sends the email the resident signed up with, or for
     * a mobile account (no email) the mobile number. Marks
     * email_verified_at or mobile_verified_at, depending on where the code
     * went, and logs the resident in.
     *
     * After MAX_WRONG_CODES wrong codes the resident has to get a new code
     * ("Resend code"), so the 6 digits can't be guessed by trying them all.
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'  => ['required_without:mobile', 'nullable', 'email'],
            'mobile' => ['required_without:email', 'nullable', 'string'],
            'otp'    => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $wrongCodes = $this->wrongCodeKey($this->loginFromRequest($request));

        if (RateLimiter::tooManyAttempts($wrongCodes, self::MAX_WRONG_CODES)) {
            return response()->json([
                'message' => 'Too many wrong codes. Tap "Resend code" to get a new one.',
            ], 429);
        }

        $citizen = $this->accountFromRequest($request)
            ->where('verification_otp', $request->otp)
            ->first();

        if (! $citizen) {
            RateLimiter::hit($wrongCodes, self::CODE_MINUTES * 60);

            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        if ($citizen->verification_otp_expires_at === null || now()->greaterThan($citizen->verification_otp_expires_at)) {
            return response()->json(['message' => 'This code has expired. Please request a new one.'], 422);
        }

        $this->clearWrongCodes($citizen);

        $bySms = $citizen->verification_otp_via === 'sms';

        $citizen->update([
            ($bySms ? 'mobile_verified_at' : 'email_verified_at') => now(),
            'verification_otp'            => null,
            'verification_otp_expires_at' => null,
            'verification_otp_via'        => null,
        ]);

        $token = $citizen->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => $bySms ? 'Mobile number verified' : 'Email verified',
            'citizen' => $citizen,
            'token'   => $token,
        ]);
    }

    /**
     * Sends a new sign-up code.
     *
     *   email given   emails it — or texts it with verify_via=sms (the login
     *                 screen's "Verify Your Email" page sends no verify_via)
     *   mobile given  texts it (mobile accounts have no email)
     *
     * The new code is saved only once it was sent, so a text that fails
     * leaves the previous code working.
     */
    public function resendVerificationOtp(Request $request, SmsService $sms)
    {
        $validator = Validator::make($request->all(), [
            'email'  => ['required_without:mobile', 'nullable', 'email'],
            'mobile' => ['required_without:email', 'nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $byEmail = $request->filled('email');
        $citizen = $this->accountFromRequest($request)->first();

        if (! $citizen) {
            return response()->json([
                'message' => $byEmail
                    ? 'If that email is registered, a new code has been sent.'
                    : 'If that number is registered, a new code has been sent.',
            ]);
        }

        if ($citizen->email_verified_at !== null) {
            return response()->json(['message' => 'This email is already verified.'], 422);
        }

        if ($citizen->mobile_verified_at !== null) {
            return response()->json(['message' => 'This account is already verified. You can log in.'], 422);
        }

        // A mobile account has no email: its code is always texted.
        $via = ($citizen->isMobileAccount() || ! $byEmail || $request->input('verify_via') === 'sms')
            ? 'sms'
            : 'email';
        $otp = (string) random_int(100000, 999999);

        $delivery = $this->sendCode($citizen, $otp, $via, $sms);

        if (! $delivery['sent']) {
            [$message, $status] = $this->notSentReply($delivery, $citizen);

            return response()->json([
                'message'     => $message,
                'verify_via'  => $via,
                'code_sent'   => false,
                'retry_after' => $delivery['retry_after'] ?? null,
            ], $status);
        }

        $citizen->update([
            'verification_otp'            => $delivery['code'],
            'verification_otp_expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'verification_otp_via'        => $delivery['via'],
        ]);

        $this->clearWrongCodes($citizen);

        return response()->json([
            'message'    => $delivery['via'] === 'sms'
                ? 'A new verification code was texted to your mobile number.'
                : 'A new verification code has been sent.',
            'verify_via' => $delivery['via'],
            'code_sent'  => true,
        ]);
    }

    /**
     * Logs in with the email OR the mobile number (field "login"; older
     * app builds send "email"). Mobile accounts have no email, so they
     * always use the number.
     */
    public function login(Request $request)
    {
        $login = $this->inputString($request, 'login');
        if ($login === '') {
            $login = $this->inputString($request, 'email');
        }
        if ($login === '') {
            $login = $this->inputString($request, 'mobile');
        }

        $validator = Validator::make(
            ['login' => $login, 'password' => $request->input('password')],
            [
                'login'    => ['required', 'string'],
                'password' => ['required', 'string'],
            ],
            ['login.required' => 'Please enter your email or mobile number.']
        );

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $citizen = Citizen::findByLogin($login);

        if (! $citizen || ! $citizen->password || ! Hash::check($request->password, $citizen->password)) {
            return response()->json([
                'message' => str_contains($login, '@')
                    ? 'Invalid email or password'
                    : 'Invalid mobile number or password',
            ], 401);
        }

        // Finished sign-up = the code was entered, by email or by text.
        if (! $citizen->hasVerifiedContact()) {
            $mobileAccount = $citizen->isMobileAccount();

            return response()->json([
                'message'            => $mobileAccount
                    ? 'Please verify your mobile number before logging in.'
                    : 'Please verify your email before logging in.',
                'needs_verification' => true,
                'email'              => $citizen->email,
                'mobile'             => $mobileAccount ? $citizen->mobile : null,
                'verify_via'         => $mobileAccount ? 'sms' : 'email',
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

        // A mobile account logs in with its number, which was confirmed with
        // a texted code — changing it here would skip that check.
        if ($citizen->isMobileAccount()
            && SmsService::normalizeNumber($this->inputString($request, 'mobile'))
                !== SmsService::normalizeNumber((string) $citizen->mobile)) {
            $message = 'Your mobile number is how you log in, so it can\'t be changed here.';

            return response()->json(['message' => $message, 'errors' => ['mobile' => [$message]]], 422);
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

    /**
     * Sends a 6-digit password-reset code.
     *
     *   via=email (default)  emails it, as before.
     *   via=sms + mobile     texts it to an account that logs in with that
     *                        mobile number — one whose number was confirmed
     *                        with a texted code (mobile_verified_at). Numbers
     *                        of residents who signed up with Email were never
     *                        confirmed, so the number alone doesn't reach
     *                        those accounts: a stranger whose number was
     *                        mistyped into someone's account would get it.
     *   via=sms + mobile     (older app builds) texted when the email and the
     *     + email            number belong to the same account; resetPassword()
     *                        then needs that email.
     *
     * Either way the reply doesn't say whether an account was found. For
     * texts, the per-number limits (1 a minute, 5 an hour) count every
     * request, found or not, so they don't give that away either.
     */
    public function forgotPassword(Request $request, SmsService $sms)
    {
        $bySms = $request->input('via') === 'sms';

        $validator = Validator::make($request->all(), [
            'email'  => $bySms ? ['nullable', 'email'] : ['required', 'email'],
            'mobile' => $bySms ? ['required', 'string', $this->textableNumberRule()] : ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        if ($bySms) {
            return $this->sendResetCodeByText($request, $sms);
        }

        $citizen = Citizen::where('email', $request->email)->first();

        // Don't reveal whether the email exists — same response either way
        if (! $citizen) {
            return response()->json(['message' => 'If that email is registered, a code has been sent.']);
        }

        $otp = (string) random_int(100000, 999999);

        $citizen->update([
            'reset_otp' => $otp,
            'reset_otp_expires_at' => now()->addMinutes(self::CODE_MINUTES),
        ]);

        $this->clearWrongResetCodes($citizen);

        Mail::to($citizen->email)->send(new PasswordResetOtp($otp, $citizen->full_name));

        return response()->json(['message' => 'If that email is registered, a code has been sent.']);
    }

    /** forgotPassword() with via=sms. */
    private function sendResetCodeByText(Request $request, SmsService $sms)
    {
        // Checked before looking anything up, so it's the same for everyone.
        if (! $sms->isAvailable()) {
            return response()->json([
                'message' => 'Text messages aren\'t available right now. Please reset your password by email instead.',
            ], 503);
        }

        $number   = SmsService::normalizeNumber($request->input('mobile'));
        $cooldown = 'reset-text:' . $number;
        $hourly   = 'reset-text-hour:' . $number;

        if (RateLimiter::tooManyAttempts($cooldown, 1)) {
            $wait = max(1, RateLimiter::availableIn($cooldown));

            return response()->json([
                'message'     => "Please wait {$wait} " . ($wait === 1 ? 'second' : 'seconds') . ' before asking for another code.',
                'retry_after' => $wait,
            ], 429);
        }

        if (RateLimiter::tooManyAttempts($hourly, 5)) {
            return response()->json([
                'message' => 'Too many codes were asked for this number. Try again later, or reset your password by email instead.',
            ], 429);
        }

        RateLimiter::hit($cooldown, 60);
        RateLimiter::hit($hourly, 3600);

        if ($request->filled('email')) {
            // Older app builds: the email and the number must match.
            $sameReply = [
                'message' => 'If the email and mobile number match an account, we texted a code to that number.',
                'via'     => 'sms',
            ];

            $citizen = Citizen::where('email', $request->input('email'))->first();

            if (! $citizen || SmsService::normalizeNumber((string) $citizen->mobile) !== $number) {
                return response()->json($sameReply);
            }
        } else {
            $sameReply = [
                'message' => 'If an account logs in with this mobile number, we texted it a code.',
                'via'     => 'sms',
            ];

            $citizen = Citizen::whereMobileNumber($number)->first();

            if (! $citizen || $citizen->mobile_verified_at === null) {
                return response()->json($sameReply);
            }
        }

        $otp  = (string) random_int(100000, 999999);
        $text = $sms->sendVerificationCode((string) $citizen->mobile, $otp, self::CODE_MINUTES, 'reset');

        if (! $text['sent']) {
            [$message, $status] = $this->notSentReply($text, $citizen);

            return response()->json(['message' => $message], $status);
        }

        $citizen->update([
            'reset_otp'            => $text['code'],
            'reset_otp_expires_at' => now()->addMinutes(self::CODE_MINUTES),
        ]);

        $this->clearWrongResetCodes($citizen);

        return response()->json($sameReply);
    }

    /** Counts wrong reset codes per account login (see resetPassword()). */
    private function wrongResetCodeKey(string $login): string
    {
        return 'reset-code-wrong:' . sha1($login);
    }

    private function clearWrongResetCodes(Citizen $citizen): void
    {
        foreach ($this->loginsOf($citizen) as $login) {
            RateLimiter::clear($this->wrongResetCodeKey($login));
        }
    }

    /**
     * Sets the new password with the reset code. The account is named by
     * its email, or by its mobile number for a mobile account.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required_without:mobile', 'nullable', 'email'],
            'mobile'   => ['required_without:email', 'nullable', 'string'],
            'otp'      => ['required', 'string'],
            // Same strengthened rule as register() — kept identical so a
            // citizen can't sidestep the stronger policy just by going
            // through the reset flow instead of signing up fresh.
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // After MAX_WRONG_CODES wrong codes, a new code is needed, so the
        // 6 digits can't be guessed by trying them all.
        $wrongCodes = $this->wrongResetCodeKey($this->loginFromRequest($request));

        if (RateLimiter::tooManyAttempts($wrongCodes, self::MAX_WRONG_CODES)) {
            return response()->json([
                'message' => 'Too many wrong codes. Tap "Resend" to get a new one.',
            ], 429);
        }

        $citizen = $this->accountFromRequest($request)
            ->where('reset_otp', $request->otp)
            ->first();

        if (! $citizen) {
            RateLimiter::hit($wrongCodes, self::CODE_MINUTES * 60);

            return response()->json(['message' => 'Invalid code.'], 422);
        }

        if ($citizen->reset_otp_expires_at === null || now()->greaterThan($citizen->reset_otp_expires_at)) {
            return response()->json(['message' => 'This code has expired. Please request a new one.'], 422);
        }

        $this->clearWrongResetCodes($citizen);

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