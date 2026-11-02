<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Texts the 6-digit sign-up code when a resident picks "Mobile Number" on
 * Create Account step 3 (Api\AuthController::register / resend), and the
 * password-reset code for Forgot Password by text
 * (Api\AuthController::forgotPassword).
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
 *
 * When the gateway fails to send a text (e.g. Semaphore hasn't approved
 * the sender name yet, no credits, no connection), texting is paused for
 * PAUSE_MINUTES: isAvailable() says no, so the app stops offering
 * "Text me a code" and uses email where it can, instead of every resident
 * running into the same error. It tries again after the pause.
 *
 * Broadcasts (item 56): every Quick Broadcast on Alerts & Broadcast is
 * texted too (Admin\AlertController::store()). broadcast() sends one text
 * to many numbers — Semaphore takes up to 1,000 in one call. An admin sends
 * it, so the limits above don't hold it back and it doesn't count toward
 * SMS_DAILY_LIMIT; it goes out even while texting is paused for codes.
 */
class SmsService
{
    /** Semaphore takes up to this many numbers in one call (api.semaphore.co/docs). */
    public const BULK_LIMIT = 1000;

    /** Numbers per call to textbee, which texts them from one phone. */
    private const TEXTBEE_BULK_LIMIT = 50;

    /** Every broadcast text starts with it: "MDRRMO Rosales: Flood Warning". */
    public const BROADCAST_FROM = 'MDRRMO Rosales';

    /** A broadcast text longer than this many texts is cut short… */
    public const BROADCAST_MAX_PARTS = 4;

    /** …and ends with this. */
    public const BROADCAST_CUT = '... Full alert in the app.';

    /** A phone counts these as two characters each (GSM's extension table). */
    public const DOUBLE = '^{}\\[]~|';

    /**
     * Turned into plain letters before the rest is transliterated (plain()),
     * so a text holds 160 characters: Unicode in a text would cut that to
     * 70. alerts.blade.php's preview uses the same list.
     */
    public const PLAIN = [
        "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{201B}" => "'",
        "\u{2032}" => "'", "\u{00B4}" => "'", '`' => "'",
        "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"', "\u{201F}" => '"',
        "\u{2033}" => '"', "\u{00AB}" => '"', "\u{00BB}" => '"',
        "\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2013}" => '-',
        "\u{2014}" => '-', "\u{2015}" => '-', "\u{2212}" => '-',
        "\u{2026}" => '...', "\u{2022}" => '-', "\u{00B7}" => '-', "\u{00D7}" => 'x',
        "\u{20B1}" => 'P', "\u{20AC}" => 'EUR',
        "\u{00DF}" => 'ss', "\u{00E6}" => 'ae', "\u{00C6}" => 'AE', "\u{0153}" => 'oe', "\u{0152}" => 'OE',
        "\u{00F8}" => 'o', "\u{00D8}" => 'O', "\u{0111}" => 'd', "\u{0110}" => 'D',
        "\u{0142}" => 'l', "\u{0141}" => 'L',
    ];

    /** Semaphore's credit balance, read at most every 5 minutes (balance()). */
    private const BALANCE_KEY = 'sms-semaphore-balance';

    /** Seconds before the same number can get another code. */
    private const COOLDOWN_SECONDS = 60;

    /** Codes one number can get in an hour. */
    private const MAX_PER_NUMBER_PER_HOUR = 5;

    /** Minutes texting stays off after the gateway failed to send. */
    public const PAUSE_MINUTES = 15;

    private const PAUSE_KEY = 'sms-gateway-paused';

    public function driver(): string
    {
        return strtolower(trim((string) config('services.sms.driver', 'none')));
    }

    /**
     * Texting is set up, isn't paused after a failed text, and today's
     * limit isn't used up.
     */
    public function isAvailable(): bool
    {
        return $this->isConfigured() && ! $this->isPaused() && ! $this->dailyLimitReached();
    }

    /** The gateway failed to send a text in the last PAUSE_MINUTES. */
    public function isPaused(): bool
    {
        return Cache::has(self::PAUSE_KEY);
    }

    /** After a failed text: no texting for PAUSE_MINUTES (see the class comment). */
    private function pause(): void
    {
        Cache::put(self::PAUSE_KEY, true, now()->addMinutes(self::PAUSE_MINUTES));

        Log::warning('SMS: texting is paused for ' . self::PAUSE_MINUTES . ' minutes after the '
            . $this->driver() . ' gateway failed to send. Codes go by email where possible meanwhile.');
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
     *   reason       why it wasn't sent: unavailable (off, or paused after
     *                a failed text), daily_limit, invalid_number, too_soon,
     *                hourly_limit, gateway_error (this one starts the pause)
     *   retry_after  seconds to wait, for too_soon / hourly_limit
     *
     * $purpose picks the wording: 'signup' ("verification code"), 'reset'
     * ("password reset code", for Forgot Password by text) or 'change'
     * ("code to confirm this number", for Change number in Personal
     * Information).
     */
    public function sendVerificationCode(string $mobile, string $code, int $minutes = 10, string $purpose = 'signup'): array
    {
        if (! $this->isConfigured() || $this->isPaused()) {
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
                'semaphore' => $this->sendWithSemaphore($number, $code, $minutes, $purpose),
                'textbee'   => $this->sendWithTextbee($number, $code, $minutes, $purpose),
                'log'       => $this->writeToLog($number, $code, $minutes, $purpose),
            };
        } catch (\Throwable $e) {
            Log::warning('SMS: could not reach the ' . $this->driver() . ' gateway.', [
                'error' => $e->getMessage(),
            ]);
            $sentCode = null;
        }

        if ($sentCode === null) {
            $this->pause();

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
    public function verificationMessage(string $code, int $minutes = 10, string $purpose = 'signup'): string
    {
        $what = match ($purpose) {
            'reset'  => 'password reset code',
            'change' => 'code to confirm this number', // Change number, in Personal Information
            default  => 'verification code',
        };

        return "ResQPulse: Your {$what} is {$code}. "
            . "It expires in {$minutes} minutes. Do not share it with anyone.";
    }

    /**
     * Texts a plain notice instead of a code — e.g. telling a resident who
     * signed up with a mobile number (no email) that their ID wasn't
     * approved. Uses Semaphore's regular route, since its OTP route is for
     * codes only. Counts toward SMS_DAILY_LIMIT. Returns whether it went out.
     */
    public function sendMessage(string $mobile, string $message): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $number = self::normalizeNumber($mobile);
        if ($number === null) {
            return false;
        }

        try {
            $sent = match ($this->driver()) {
                'semaphore' => $this->postToSemaphore($number, $message, null) !== null,
                'textbee'   => $this->postToTextbee($number, $message),
                'log'       => $this->logText($number, $message),
            };
        } catch (\Throwable $e) {
            Log::warning('SMS: could not reach the ' . $this->driver() . ' gateway.', [
                'error' => $e->getMessage(),
            ]);
            $sent = false;
        }

        if ($sent) {
            RateLimiter::hit($this->dailyKey(), 86400);
        } else {
            $this->pause();
        }

        return $sent;
    }

    /**
     * Semaphore — https://semaphore.co/docs
     *
     * The OTP route (default) is delivered ahead of other traffic and costs
     * 2 credits per text; SEMAPHORE_ROUTE=regular costs 1 credit but can be
     * slower when Semaphore is busy. Returns the code that was texted, or
     * null when Semaphore didn't take the message.
     */
    private function sendWithSemaphore(string $number, string $code, int $minutes, string $purpose): ?string
    {
        $otpRoute = strtolower((string) config('services.semaphore.route', 'otp')) !== 'regular';

        // On the OTP route Semaphore puts the code where {otp} is.
        $message = $this->verificationMessage($otpRoute ? '{otp}' : $code, $minutes, $purpose);
        $sent    = $this->postToSemaphore($number, $message, $otpRoute ? $code : null);

        if ($sent === null) {
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
     * Posts one text to Semaphore: on the OTP route when $otpCode is given,
     * otherwise on the regular route. Returns Semaphore's entry for the
     * message, or null when it didn't take it.
     */
    private function postToSemaphore(string $number, string $message, ?string $otpCode): ?array
    {
        $params = [
            'apikey'  => config('services.semaphore.api_key'),
            'number'  => '0' . substr($number, 2), // 09171234567, as in Semaphore's examples
            'message' => $message,
        ];

        if ($otpCode !== null) {
            $params['code'] = $otpCode;
        }

        // Without it Semaphore uses the account's default sender name.
        if (filled(config('services.semaphore.sender_name'))) {
            $params['sendername'] = config('services.semaphore.sender_name');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(8)
            ->post('https://api.semaphore.co/api/v4/' . ($otpCode !== null ? 'otp' : 'messages'), $params);

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

        return $sent;
    }

    /**
     * textbee — https://textbee.dev/docs/sending-sms
     *
     * The text is sent by the textbee app on an Android phone, using that
     * phone's SIM. Returns the code, or null when textbee didn't take it.
     */
    private function sendWithTextbee(string $number, string $code, int $minutes, string $purpose): ?string
    {
        return $this->postToTextbee($number, $this->verificationMessage($code, $minutes, $purpose))
            ? $code
            : null;
    }

    /** One number, or several (a broadcast). */
    private function postToTextbee(string|array $numbers, string $message): bool
    {
        $body = [
            'recipients' => array_map(fn ($number) => '+' . $number, (array) $numbers),
            'message'    => $message,
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

            return false;
        }

        return true;
    }

    /** SMS_DRIVER=log — for trying the flow without spending credits. */
    private function writeToLog(string $number, string $code, int $minutes, string $purpose): string
    {
        $this->logText($number, $this->verificationMessage($code, $minutes, $purpose));

        return $code;
    }

    private function logText(string $number, string $message): bool
    {
        Log::info('SMS (not sent, SMS_DRIVER=log) to +' . $number . ': ' . $message);

        return true;
    }

    private function logRejected(string $gateway, int $status, string $body): void
    {
        Log::warning("SMS: {$gateway} did not send the text.", [
            'status' => $status,
            'reply'  => Str::limit($body, 500),
        ]);
    }

    private function notSent(string $reason, ?int $retryAfter = null): array
    {
        return ['sent' => false, 'code' => null, 'reason' => $reason, 'retry_after' => $retryAfter];
    }

    // ── Broadcasts (item 56) ──────────────────────────────────────────

    /**
     * Broadcasts can be texted: a gateway is set up. Unlike codes, a
     * broadcast isn't held back by the pause or SMS_DAILY_LIMIT.
     */
    public function canBroadcast(): bool
    {
        return $this->isConfigured();
    }

    /**
     * Semaphore credits for each text of a broadcast: 1 on the regular
     * route, 2 on the priority route (SEMAPHORE_BROADCAST_ROUTE=priority).
     * Null for textbee (the phone's own load) and log (nothing is sent).
     */
    public function creditsPerText(): ?int
    {
        if ($this->driver() !== 'semaphore') {
            return null;
        }

        return $this->priorityBroadcasts() ? 2 : 1;
    }

    private function priorityBroadcasts(): bool
    {
        return strtolower(trim((string) config('services.semaphore.broadcast_route', 'regular'))) === 'priority';
    }

    /**
     * Semaphore's credit balance, read at most every 5 minutes (Semaphore
     * allows 2 reads a minute) and again after a broadcast. Null when the
     * gateway isn't Semaphore or the balance couldn't be read.
     */
    public function balance(): ?int
    {
        if ($this->driver() !== 'semaphore' || ! $this->isConfigured()) {
            return null;
        }

        $balance = Cache::remember(self::BALANCE_KEY, now()->addMinutes(5), function () {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get('https://api.semaphore.co/api/v4/account', [
                        'apikey' => config('services.semaphore.api_key'),
                    ]);
                $credits = $response->json('credit_balance') ?? $response->json('0.credit_balance');

                return $response->successful() && is_numeric($credits) ? (int) floor((float) $credits) : -1;
            } catch (\Throwable $e) {
                return -1; // unknown — not asked again for 5 minutes
            }
        });

        return $balance >= 0 ? $balance : null;
    }

    /**
     * Texts one broadcast to many numbers (639XXXXXXXXX, from
     * normalizeNumber()), up to BULK_LIMIT per call. Returns:
     *
     *   sent     numbers the gateway took
     *   failed   numbers it didn't
     *   credits  about how many Semaphore credits it used (0 for textbee
     *            and log)
     *   error    what went wrong, in a line for the admin, when some
     *            weren't sent
     *
     * When none went out, texting is paused like after a failed code.
     */
    public function broadcast(array $numbers, string $message): array
    {
        $numbers = array_values(array_unique(array_filter($numbers)));
        $result  = ['sent' => 0, 'failed' => 0, 'credits' => 0, 'error' => null];

        if ($numbers === [] || ! $this->canBroadcast()) {
            return $result;
        }

        $chunks = array_chunk($numbers, $this->driver() === 'textbee' ? self::TEXTBEE_BULK_LIMIT : self::BULK_LIMIT);
        if (count($chunks) > 1) {
            @set_time_limit(60 + 30 * count($chunks)); // each call can take a while
        }

        foreach ($chunks as $chunk) {
            try {
                [$sent, $error] = match ($this->driver()) {
                    'semaphore' => $this->bulkToSemaphore($chunk, $message),
                    'textbee'   => $this->postToTextbee($chunk, $message)
                        ? [count($chunk), null]
                        : [0, "textbee didn't send the text."],
                    'log'       => $this->logBroadcast($chunk, $message),
                };
            } catch (\Throwable $e) {
                Log::warning('SMS: could not reach the ' . $this->driver() . ' gateway for a broadcast.', [
                    'error' => $e->getMessage(),
                ]);
                [$sent, $error] = [0, ucfirst($this->driver()) . " couldn't be reached."];
            }

            $result['sent']   += $sent;
            $result['failed'] += count($chunk) - $sent;
            $result['error']  ??= $error;
        }

        $result['credits'] = $result['sent'] * self::parts($message) * (int) $this->creditsPerText();

        if ($result['sent'] === 0) {
            $this->pause(); // the gateway isn't sending: codes go by email meanwhile
        }
        Cache::forget(self::BALANCE_KEY);

        return $result;
    }

    /**
     * One call to Semaphore with up to BULK_LIMIT numbers, on the regular
     * route (or priority). Returns how many it took, and what went wrong
     * when it didn't take them all.
     *
     * @return array{0: int, 1: ?string}
     */
    private function bulkToSemaphore(array $numbers, string $message): array
    {
        $params = [
            'apikey'  => config('services.semaphore.api_key'),
            'number'  => implode(',', array_map(fn ($number) => '0' . substr($number, 2), $numbers)),
            'message' => $message,
        ];

        if (filled(config('services.semaphore.sender_name'))) {
            $params['sendername'] = config('services.semaphore.sender_name');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(30)
            ->post('https://api.semaphore.co/api/v4/' . ($this->priorityBroadcasts() ? 'priority' : 'messages'), $params);

        // On success Semaphore answers with a list: one entry per number.
        $entries = $response->json();

        if (! $response->successful() || ! is_array($entries) || ! array_is_list($entries)) {
            $this->logRejected('semaphore', $response->status(), $response->body());

            return [0, $this->semaphoreSaid($response)];
        }

        $taken = min(count($numbers), count(array_filter($entries, fn ($entry) => is_array($entry)
            && ! empty($entry['message_id'])
            && ! in_array(strtolower((string) ($entry['status'] ?? '')), ['failed', 'refunded'], true))));

        if ($taken < count($numbers)) {
            $this->logRejected('semaphore', $response->status(), $response->body());

            return [$taken, "Semaphore didn't take " . (count($numbers) - $taken) . ' of the numbers.'];
        }

        return [$taken, null];
    }

    /** What Semaphore said when it refused, in a line for the admin — never the API key. */
    private function semaphoreSaid(\Illuminate\Http\Client\Response $response): string
    {
        $said = null;
        $json = $response->json();

        if (is_array($json)) {
            array_walk_recursive($json, function ($value) use (&$said) {
                if ($said === null && is_string($value) && trim($value) !== '') {
                    $said = trim($value);
                }
            });
        }

        $said = $said !== null
            ? rtrim(Str::limit(strip_tags($said), 120), '. ') . '.'
            : ($response->successful() ? "it didn't take the text." : 'error ' . $response->status() . '.');

        return 'Semaphore: ' . str_replace((string) config('services.semaphore.api_key'), '***', $said);
    }

    /** SMS_DRIVER=log: the broadcast is written to the log, not sent. */
    private function logBroadcast(array $numbers, string $message): array
    {
        $shown = implode(', ', array_map(fn ($number) => '+' . $number, array_slice($numbers, 0, 10)));
        $more  = count($numbers) > 10 ? ' and ' . (count($numbers) - 10) . ' more' : '';

        Log::info("SMS broadcast (not sent, SMS_DRIVER=log) to {$shown}{$more}: {$message}");

        return [count($numbers), null];
    }

    /**
     * The text of a broadcast: who it's from and the title, the place when
     * one was picked, then the message —
     *
     *   MDRRMO Rosales: Flood Warning
     *   Brgy. Carmen East, Rosales
     *   Flooding is expected in low-lying areas. Residents in ...
     *
     * In plain letters (plain()), so each text holds 160 characters. Past
     * BROADCAST_MAX_PARTS texts it's cut at a word and ends with
     * BROADCAST_CUT. alerts.blade.php builds the same text for its preview
     * (smsText()); keep the two the same.
     */
    public static function broadcastText(string $title, ?string $where, string $body): string
    {
        $lines = [self::BROADCAST_FROM . ': ' . self::plain($title), self::plain((string) $where)];

        foreach (preg_split('/\R/u', $body) ?: [] as $line) {
            $lines[] = self::plain($line);
        }

        return self::shorten(implode("\n", array_filter($lines, fn ($line) => $line !== '')));
    }

    /**
     * One line in plain letters: “quotes”, dashes, ñ and é become ", -, n
     * and e; emoji and other symbols are left out; spaces are single.
     */
    public static function plain(string $text): string
    {
        $text = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $text) ?? $text;
        $text = preg_replace('/[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u', ' ', $text) ?? $text;
        $text = Str::ascii(strtr($text, self::PLAIN), 'en');
        $text = preg_replace('/[^\x20-\x7E]+/', '', $text) ?? '';

        return trim(preg_replace('/ {2,}/', ' ', $text) ?? '');
    }

    /** The characters as a phone counts them: those in DOUBLE take two. */
    public static function units(string $text): int
    {
        return strlen($text) + (strlen($text) - strlen(str_replace(str_split(self::DOUBLE), '', $text)));
    }

    /** How many texts it goes as: one holds 160 characters, or 153 each when it's longer. */
    public static function parts(string $text): int
    {
        $units = self::units($text);

        return $units <= 160 ? 1 : (int) ceil($units / 153);
    }

    /** Cut to BROADCAST_MAX_PARTS texts, at a word, ending with BROADCAST_CUT. */
    private static function shorten(string $text): string
    {
        if (self::parts($text) <= self::BROADCAST_MAX_PARTS) {
            return $text;
        }

        $room  = self::BROADCAST_MAX_PARTS * 153 - self::units(self::BROADCAST_CUT);
        $units = 0;
        $end   = 0;
        while ($end < strlen($text)) {
            $units += str_contains(self::DOUBLE, $text[$end]) ? 2 : 1;
            if ($units > $room) {
                break;
            }
            $end++;
        }

        $cut = substr($text, 0, $end);

        // Back to the end of the last whole sentence when it's near; else,
        // mid-word, back to the word before (unless that's too far back).
        $look = $cut . $text[$end];
        $stop = -1;
        foreach (['. ', '! ', '? ', ".\n", "!\n", "?\n"] as $mark) {
            $at   = strrpos($look, $mark);
            $stop = $at !== false && $at > $stop ? $at : $stop;
        }

        if ($stop >= 0 && $stop >= strlen($cut) - 100) {
            $cut = substr($cut, 0, $stop);
        } elseif ($text[$end] !== ' ' && $text[$end] !== "\n") {
            $space = max((int) strrpos($cut, ' '), (int) strrpos($cut, "\n"));
            if ($space > strlen($cut) - 30) {
                $cut = substr($cut, 0, $space);
            }
        }

        return rtrim($cut, " \n.,;:-") . self::BROADCAST_CUT;
    }

    /** One counter per Philippine calendar day. */
    private function dailyKey(): string
    {
        return 'sms-codes-day:' . now('Asia/Manila')->toDateString();
    }
}