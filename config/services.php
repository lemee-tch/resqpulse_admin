<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    // These were two separate 'postmark' entries before — in a PHP array
    // literal, a repeated key doesn't merge, it silently overwrites the
    // earlier one. 'token' was dead/unreachable as a result. Not used by
    // this app right now (mail goes through SMTP), but worth being
    // correct rather than leaving a silent bug sitting here.
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
        'key'   => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'maps_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        // gemini-2.0-flash (the old fallback here) was shut down in June
        // 2026 — updated to a model that's actually still alive, so a
        // missing GEMINI_VISION_MODEL env var fails safely instead of
        // silently pointing at a dead model.
        'model'   => env('GEMINI_VISION_MODEL', 'gemini-3.1-flash-lite'),
    ],

    /*
    | Text messages — the 6-digit sign-up code when a resident picks
    | "Mobile Number" on Create Account step 3 (App\Services\SmsService).
    |
    | SMS_DRIVER:
    |   none       (default) texting is off; the app shows Email only
    |   semaphore  Semaphore (semaphore.co) — needs an API key and an
    |              approved sender name
    |   textbee    textbee.dev — sends from an Android phone with a SIM
    |   log        writes the text to storage/logs/laravel.log instead of
    |              sending it — for testing without credits only
    |
    | SMS_DAILY_LIMIT caps how many codes are texted per day, so nobody can
    | use up the credits. Past it, codes go by email until the next day.
    */
    'sms' => [
        'driver'      => env('SMS_DRIVER', 'none'),
        'daily_limit' => (int) env('SMS_DAILY_LIMIT', 100),
    ],

    'semaphore' => [
        'api_key'     => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME'),
        // otp     = Semaphore's OTP route: delivered first, 2 credits per text
        // regular = normal route: 1 credit per text, can be slower when busy
        'route'       => env('SEMAPHORE_ROUTE', 'otp'),
    ],

    'textbee' => [
        'api_key'   => env('TEXTBEE_API_KEY'),
        'device_id' => env('TEXTBEE_DEVICE_ID'),
    ],

];