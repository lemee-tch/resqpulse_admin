<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactChangeOtp;
use App\Models\Citizen;
use App\Services\AuditLogService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * Residents app → Profile → Personal Information → Change email / Change
 * number. A logged-in resident changes the email or mobile number on their
 * account, confirmed with a 6-digit code sent to the NEW email or number:
 *
 *   POST /api/profile/contact          {type: email|mobile, value}
 *        sends the code and keeps the change waiting for 10 minutes.
 *   POST /api/profile/contact/verify   {type, code}
 *        checks the code and saves the change.
 *
 * Email: the code goes to the new email. A resident who signed up with a
 * mobile number (no email) can add one the same way.
 *
 * Mobile number: the code is texted to the new number, and the number is
 * then marked as confirmed (mobile_verified_at). When texts can't be sent
 * (texting off, the gateway failing, the daily limit), a resident who has
 * an email gets the code there instead: that confirms it's really them,
 * but not the number, so the number is saved as not confirmed. A resident
 * without an email (they log in with their number) has to wait for
 * texting to work.
 *
 * Sending the current number (or email) again verifies it, when it isn't
 * verified yet. That's how an email account confirms its number.
 *
 * Before this, Personal Information changed the number with no code at all
 * (and refused for residents who log in with their number), and the email
 * couldn't be changed. AuthController::updateProfile() no longer changes
 * the number.
 *
 * The waiting change is kept in the cache (like the codes' rate limits),
 * so there's no new database column. The code itself is stored hashed.
 */
class ContactChangeController extends Controller
{
    /** Minutes a code stays valid. */
    private const CODE_MINUTES = 10;

    /** Wrong codes allowed before the resident has to ask for a new one. */
    private const MAX_WRONG_CODES = 5;

    /** Seconds before the resident can ask for another code. */
    private const COOLDOWN_SECONDS = 60;

    /** Codes a resident can ask for in an hour. */
    private const MAX_CODES_PER_HOUR = 5;

    public function send(Request $request, SmsService $sms)
    {
        $citizen = $request->user();

        if (! $citizen instanceof Citizen) {
            return response()->json(['message' => 'Not a citizen account.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'type'  => ['required', 'in:email,mobile'],
            'value' => ['required', 'string', 'max:255'],
        ], [
            'value.required' => $request->input('type') === 'mobile'
                ? 'Enter your new mobile number.'
                : 'Enter your new email.',
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator->errors()->first());
        }

        $type = (string) $request->input('type');

        // The resident asked for codes too often.
        $cooldownKey = $this->cooldownKey($citizen, $type);
        $hourlyKey   = 'contact-code-hour:' . $citizen->id;

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $wait = max(1, RateLimiter::availableIn($cooldownKey));

            return response()->json([
                'message'     => "Please wait {$wait} " . ($wait === 1 ? 'second' : 'seconds') . ' before asking for another code.',
                'retry_after' => $wait,
            ], 429);
        }
        if (RateLimiter::tooManyAttempts($hourlyKey, self::MAX_CODES_PER_HOUR)) {
            return response()->json([
                'message'     => 'Too many codes were sent. Please try again in an hour.',
                'retry_after' => RateLimiter::availableIn($hourlyKey),
            ], 429);
        }

        $code = (string) random_int(100000, 999999);

        if ($type === 'email') {
            $value = strtolower(trim((string) $request->input('value')));

            if (Validator::make(['email' => $value], ['email' => ['email']])->fails()) {
                return $this->invalid('Enter a valid email address.');
            }

            $same = strcasecmp($value, trim((string) $citizen->email)) === 0;

            if ($same && $citizen->email_verified_at !== null) {
                return $this->invalid('This is already your email.');
            }
            if (! $same && Citizen::where('email', $value)->whereKeyNot($citizen->id)->exists()) {
                return $this->invalid('This email is already used by another account.');
            }

            try {
                Mail::to($value)->send(new ContactChangeOtp($code, (string) $citizen->full_name, 'email'));
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'message' => 'We couldn\'t send the email right now. Please try again in a minute.',
                ], 503);
            }

            return $this->codeSent($citizen, $type, $value, $code, 'email', $value, $same
                ? "We sent a 6-digit code to {$value}."
                : "We sent a 6-digit code to {$value}. Enter it to change your email.");
        }

        // Mobile number
        $number = SmsService::normalizeNumber((string) $request->input('value')); // 639XXXXXXXXX

        if ($number === null) {
            return $this->invalid('Enter a valid 11-digit mobile number (09XXXXXXXXX).');
        }

        $local = '0' . substr($number, 2); // 09XXXXXXXXX
        $same  = SmsService::normalizeNumber((string) $citizen->mobile) === $number;

        if ($same && $citizen->mobile_verified_at !== null) {
            return $this->invalid('This number is already verified on your account.');
        }
        if (! $same && Citizen::whereMobileNumber($number)->whereKeyNot($citizen->id)->exists()) {
            return $this->invalid('This mobile number is already used by another account.');
        }

        $text = $sms->sendVerificationCode($local, $code, self::CODE_MINUTES, 'change');

        if ($text['sent']) {
            return $this->codeSent($citizen, $type, $local, $text['code'], 'sms', $local,
                "We texted a 6-digit code to {$local}.");
        }

        // The number got a code too recently, or isn't one we can text.
        if (in_array($text['reason'], ['too_soon', 'hourly_limit'], true)) {
            $wait = max(1, (int) ($text['retry_after'] ?? self::COOLDOWN_SECONDS));

            return response()->json([
                'message'     => $text['reason'] === 'too_soon'
                    ? "Please wait {$wait} " . ($wait === 1 ? 'second' : 'seconds') . ' before asking for another code.'
                    : 'Too many codes were sent to this number. Please try again later.',
                'retry_after' => $wait,
            ], 429);
        }
        if ($text['reason'] === 'invalid_number') {
            return $this->invalid("We can't text {$local}. Check the number.");
        }

        // Texting is off or failing. Confirming the number itself needs a
        // text; a resident with an email can still change it, confirmed
        // with a code sent to their email.
        if ($same) {
            return response()->json([
                'message' => 'Text messages aren\'t available right now, so your number can\'t be verified yet. Please try again later.',
            ], 503);
        }
        if ($citizen->isMobileAccount()) {
            return response()->json([
                'message' => 'Text messages aren\'t available right now. Please try again later.',
            ], 503);
        }

        $email = trim((string) $citizen->email);

        try {
            Mail::to($email)->send(new ContactChangeOtp($code, (string) $citizen->full_name, 'mobile', $local));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'We couldn\'t send the code right now. Please try again in a minute.',
            ], 503);
        }

        return $this->codeSent($citizen, $type, $local, $code, 'email', $email,
            "Text messages aren't available right now, so we sent the code to your email ({$email}) instead. "
            . 'Your new number will show as not verified until it can be confirmed by text.');
    }

    public function verify(Request $request)
    {
        $citizen = $request->user();

        if (! $citizen instanceof Citizen) {
            return response()->json(['message' => 'Not a citizen account.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:email,mobile'],
            'code' => ['required', 'string'],
        ], [
            'code.required' => 'Enter the 6-digit code.',
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator->errors()->first());
        }

        $type     = (string) $request->input('type');
        $wrongKey = $this->wrongKey($citizen, $type);

        if (RateLimiter::tooManyAttempts($wrongKey, self::MAX_WRONG_CODES)) {
            return response()->json([
                'message' => 'Too many wrong codes. Tap "Resend code" to get a new one.',
            ], 429);
        }

        $pending = Cache::get($this->pendingKey($citizen, $type));

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return $this->invalid('This code has expired. Tap "Resend code" to get a new one.');
        }

        $code = preg_replace('/\D+/', '', (string) $request->input('code'));

        if ($code === '' || ! Hash::check($code, (string) $pending['code'])) {
            RateLimiter::hit($wrongKey, self::CODE_MINUTES * 60);

            return $this->invalid('That code isn\'t right. Check it and try again.');
        }

        $value = (string) $pending['value'];

        // Someone else may have taken it in the meantime.
        $taken = $type === 'email'
            ? Citizen::where('email', $value)->whereKeyNot($citizen->id)->exists()
            : Citizen::whereMobileNumber($value)->whereKeyNot($citizen->id)->exists();

        if ($taken) {
            Cache::forget($this->pendingKey($citizen, $type));

            return $this->invalid($type === 'email'
                ? 'This email is already used by another account.'
                : 'This mobile number is already used by another account.');
        }

        $old = $citizen->only([$type]);

        if ($type === 'email') {
            $same = strcasecmp($value, trim((string) $citizen->email)) === 0;

            $citizen->update([
                'email'             => $value,
                'email_verified_at' => now(),
            ]);

            $message     = $same ? 'Email verified.' : 'Email updated.';
            $description = $same
                ? "Citizen {$citizen->full_name} verified their email with a code."
                : "Citizen {$citizen->full_name} changed their email (confirmed with a code sent to the new email).";
        } else {
            $same     = SmsService::normalizeNumber((string) $citizen->mobile) === SmsService::normalizeNumber($value);
            $byText   = ($pending['via'] ?? null) === 'sms';
            $loginNow = $citizen->isMobileAccount();

            $citizen->update([
                'mobile'             => $value,
                // Confirmed only when the code was texted to the number.
                'mobile_verified_at' => $byText ? now() : null,
            ]);

            $message = match (true) {
                $same      => 'Mobile number verified.',
                ! $byText  => 'Mobile number updated. It will show as not verified until it can be confirmed by text.',
                $loginNow  => 'Mobile number updated. Use your new number the next time you log in.',
                default    => 'Mobile number updated.',
            };
            $description = match (true) {
                $same     => "Citizen {$citizen->full_name} verified their mobile number by text.",
                $byText   => "Citizen {$citizen->full_name} changed their mobile number (confirmed by text).",
                default   => "Citizen {$citizen->full_name} changed their mobile number (code sent to their email; the new number isn't confirmed by text yet).",
            };
        }

        Cache::forget($this->pendingKey($citizen, $type));
        RateLimiter::clear($wrongKey);

        AuditLogService::log('updated', $description, $citizen, $old, $citizen->only([$type]));

        return response()->json([
            'message' => $message,
            'citizen' => $citizen->fresh(),
        ]);
    }

    /**
     * Keeps the change waiting for its code, counts the code toward the
     * resident's limits, and tells the app where the code went.
     */
    private function codeSent(Citizen $citizen, string $type, string $value, string $code, string $via, string $sentTo, string $message)
    {
        Cache::put($this->pendingKey($citizen, $type), [
            'value'      => $value,
            'code'       => Hash::make($code),
            'via'        => $via,
            'expires_at' => now()->addMinutes(self::CODE_MINUTES)->getTimestamp(),
        ], now()->addMinutes(self::CODE_MINUTES));

        RateLimiter::hit($this->cooldownKey($citizen, $type), self::COOLDOWN_SECONDS);
        RateLimiter::hit('contact-code-hour:' . $citizen->id, 3600);
        RateLimiter::clear($this->wrongKey($citizen, $type));

        return response()->json([
            'message'         => $message,
            'type'            => $type,
            'sent_via'        => $via,     // email | sms
            'sent_to'         => $sentTo,  // where the code went
            'expires_minutes' => self::CODE_MINUTES,
            'resend_after'    => self::COOLDOWN_SECONDS,
        ]);
    }

    private function pendingKey(Citizen $citizen, string $type): string
    {
        return "contact-change:{$citizen->id}:{$type}";
    }

    /** One code a minute for each kind (email, mobile). */
    private function cooldownKey(Citizen $citizen, string $type): string
    {
        return "contact-code:{$citizen->id}:{$type}";
    }

    private function wrongKey(Citizen $citizen, string $type): string
    {
        return "contact-change-wrong:{$citizen->id}:{$type}";
    }

    private function invalid(string $message)
    {
        return response()->json([
            'message' => $message,
            'errors'  => ['value' => [$message]],
        ], 422);
    }
}
