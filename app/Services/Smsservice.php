<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Texts the 6-digit sign-up code when a resident picks "Mobile Number" on
 * Create Account step 3 (Api\AuthController::register / resend).
 *
 * The gateway comes from SMS_DRIVER in .env (config/services.php):
 *   semaphore  Semaphore (semaphore.co), the usual Philippine SMS API
 *   textbee    textbee.dev, sends from an Android phone with a SIM card
 *   log        writes the text to storage/logs/laravel.log — testing only
 *   none       texting is off (default); the app offers Email only
 *
 * Limits, so texting can't be abused or run up the bill:
 *   - one code per mobile number per minute (the app's "Resend in 60s"),
 *   - five codes per mobile number per hour,
 *   - SMS_DAILY_LIMIT codes per day for everyone together.
 * Only texts that were actually sent count.
 */
class SmsService
{
    /** Seconds before the same number can get another code. */
    private const COOLDOWN_SECONDS = 60;

    /** Codes one number can get in an hour. */
    private const MAX_PER_NUMBER_PER_HOUR = 5;

    public function driver(): string
    {
        return strtolower(trim((string) config('services.sms.driver', 'none')));
    }

    /** Texting is set up and today's limit isn't used up. */
    public function isAvailable(): bool
    {
        return $this->isConfigured() && ! $this->dailyLimitReached();
    }

    /** The chosen gateway has what it needs (an API key). */
    public function isConfigured(): bool
    {
        return match ($this->driver()) {
            'semaphore' => filled(config('services.semaphore.api_key')),
            'textbee'   => filled(config('services.textbee.api_key')),
            'log'       => true,
            default     => false,
        };
    }

    public function dailyLimitReached(): bool
    {
        $limit = (int) config('services.sms.daily_limit', 100);

        // 0 or less = no daily limit.
        return $limit > 0 && RateLimiter::attempts($this->dailyKey()) >= $limit;
    }

    /**
     * "09171234567", "+63 917 123 4567" or "9171234567" → "639171234567".
     * Null when it isn't a Philippine mobile number.
     */
    public static function normalizeNumber(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);

        if (preg_match('/^09\d{9}$/', $digits)) {
            return '63' . substr($digits, 1);
        }
        if (preg_match('/^639\d{9}$/', $digits)) {
            return $digits;
        }
        if (preg_match('/^9\d{9}$/', $digits)) {
            return '63' . $digits;
        }

        return null;
    }

    /**
     * Texts the sign-up code to $mobile.
     *
     * Returns:
     *   sent         whether the text went out
     *   code         the code that was texted. Usually $code, but
     *                Semaphore's OTP route makes its own code if it ignores
     *                ours, and its reply says which one it sent.
     *   reason       why it wasn't sent: unavailable, daily_limit,
     *                invalid_number, too_soon, hourly_limit, gateway_error
     *   retry_after  seconds to wait, for too_soon / hourly_limit
     */
    public function sendVerificationCode(string $mobile, string $code, int $minutes = 10): array
    {
        if (! $this->isConfigured()) {
            return $this->notSent('unavailable');
        }
        if ($this->dailyLimitReached()) {
            return $this->notSent('daily_limit');
        }

        $number = self::normalizeNumber($mobile);
        if ($number === null) {
            return $this->notSent('invalid_number');
        }

        $cooldownKey = 'sms-code:' . $number;
        $hourlyKey   = 'sms-code-hour:' . $number;

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            return $this->notSent('too_soon', RateLimiter::availableIn($cooldownKey));
        }
        if (RateLimiter::tooManyAttempts($hourlyKey, self::MAX_PER_NUMBER_PER_HOUR)) {
            return $this->notSent('hourly_limit', RateLimiter::availableIn($hourlyKey));
        }

        try {
            $sentCode = match ($this->driver()) {
                'semaphore' => $this->sendWithSemaphore($number, $code, $minutes),
                'textbee'   => $this->sendWithTextbee($number, $code, $minutes),
                'log'       => $this->writeToLog($number, $code, $minutes),
            };
        } catch (\Throwable $e) {
            Log::warning('SMS: could not reach the ' . $this->driver() . ' gateway.', [
                'error' => $e->getMessage(),
            ]);
            $sentCode = null;
        }

        if ($sentCode === null) {
            return $this->notSent('gateway_error');
        }

        RateLimiter::hit($cooldownKey, self::COOLDOWN_SECONDS);
        RateLimiter::hit($hourlyKey, 3600);
        RateLimiter::hit($this->dailyKey(), 86400);

        return ['sent' => true, 'code' => $sentCode, 'reason' => null, 'retry_after' => null];
    }

    /**
     * The text itself — plain letters only and under 160 characters, so it
     * goes as one message (one charge).
     */
    public function verificationMessage(string $code, int $minutes = 10): string
    {
        return "ResQPulse: Your verification code is {$code}. "
            . "It expires in {$minutes} minutes. Do not share it with anyone.";
    }

    /**
     * Semaphore — https://semaphore.co/docs
     *
     * The OTP route (default) is delivered ahead of other traffic and costs
     * 2 credits per text; SEMAPHORE_ROUTE=regular costs 1 credit but can be
     * slower when Semaphore is busy. Returns the code that was texted, or
     * null when Semaphore didn't take the message.
     */
    private function sendWithSemaphore(string $number, string $code, int $minutes): ?string
    {
        $otpRoute = strtolower((string) config('services.semaphore.route', 'otp')) !== 'regular';

        $params = [
            'apikey'  => config('services.semaphore.api_key'),
            'number'  => '0' . substr($number, 2), // 09171234567, as in Semaphore's examples
            // On the OTP route Semaphore puts the code where {otp} is.
            'message' => $this->verificationMessage($otpRoute ? '{otp}' : $code, $minutes),
        ];

        if ($otpRoute) {
            $params['code'] = $code;
        }

        // Without it Semaphore uses the account's default sender name.
        if (filled(config('services.semaphore.sender_name'))) {
            $params['sendername'] = config('services.semaphore.sender_name');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(8)
            ->post('https://api.semaphore.co/api/v4/' . ($otpRoute ? 'otp' : 'messages'), $params);

        // On success Semaphore answers with a list: one entry per number.
        $sent   = $response->json('0');
        $status = is_array($sent) ? strtolower((string) ($sent['status'] ?? '')) : '';

        if (! $response->successful()
            || ! is_array($sent)
            || empty($sent['message_id'])
            || in_array($status, ['failed', 'refunded'], true)) {
            $this->logRejected('semaphore', $response->status(), $response->body());

            return null;
        }

        if (! $otpRoute || ! isset($sent['code']) || ! is_numeric($sent['code'])) {
            return $code;
        }

        // Semaphore returns the code as a number, so a leading 0 is lost.
        $texted = str_pad((string) $sent['code'], strlen($code), '0', STR_PAD_LEFT);

        return $texted === $code ? $code : $texted;
    }

    /**
     * textbee — https://textbee.dev/docs/sending-sms
     *
     * The text is sent by the textbee app on an Android phone, using that
     * phone's SIM. Returns the code, or null when textbee didn't take it.
     */
    private function sendWithTextbee(string $number, string $code, int $minutes): ?string
    {
        $body = [
            'recipients' => ['+' . $number],
            'message'    => $this->verificationMessage($code, $minutes),
        ];

        // Optional — without it textbee uses the account's default phone.
        if (filled(config('services.textbee.device_id'))) {
            $body['deviceId'] = (string) config('services.textbee.device_id');
        }

        $response = Http::withHeaders(['x-api-key' => (string) config('services.textbee.api_key')])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(8)
            ->post('https://api.textbee.dev/api/v1/gateway/send-sms', $body);

        if (! $response->successful() || $response->json('data.success') === false) {
            $this->logRejected('textbee', $response->status(), $response->body());

            return null;
        }

        return $code;
    }

    /** SMS_DRIVER=log — for trying the flow without spending credits. */
    private function writeToLog(string $number, string $code, int $minutes): string
    {
        Log::info('SMS (not sent, SMS_DRIVER=log) to +' . $number . ': '
            . $this->verificationMessage($code, $minutes));

        return $code;
    }

    private function logRejected(string $gateway, int $status, string $body): void
    {
        Log::warning("SMS: {$gateway} did not send the code.", [
            'status' => $status,
            'reply'  => Str::limit($body, 500),
        ]);
    }

    private function notSent(string $reason, ?int $retryAfter = null): array
    {
        return ['sent' => false, 'code' => null, 'reason' => $reason, 'retry_after' => $retryAfter];
    }

    /** One counter per Philippine calendar day. */
    private function dailyKey(): string
    {
        return 'sms-codes-day:' . now('Asia/Manila')->toDateString();
    }
}