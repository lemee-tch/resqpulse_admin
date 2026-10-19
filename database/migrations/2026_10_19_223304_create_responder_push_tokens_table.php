<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One push token per phone signed in to a responder account.
 *
 * Each team shares one account (BFP, PNP, SARS, HCU, MSWD — see
 * ResponderSeeder), and any number of staff stay signed in on their own
 * phones. responders.fcm_token holds a single token, so only the phone that
 * opened the app last got the incident alerts. Now every phone's token is
 * kept here (POST /api/fcm-token), and signing out removes that phone's
 * token (POST /api/responder/logout).
 *
 * The token each account already has is copied in, so no phone stops
 * getting alerts after the update.
 *
 * Runs by itself on deploy (php artisan migrate --force). The same change
 * by hand, in phpMyAdmin → SQL:
 *
 *   CREATE TABLE responder_push_tokens (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     responder_id BIGINT UNSIGNED NOT NULL,
 *     token VARCHAR(512) NOT NULL UNIQUE,
 *     created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 *     FOREIGN KEY (responder_id) REFERENCES responders(id) ON DELETE CASCADE
 *   );
 *   INSERT INTO responder_push_tokens (responder_id, token, created_at, updated_at)
 *     SELECT id, fcm_token, NOW(), NOW() FROM responders WHERE fcm_token IS NOT NULL AND fcm_token <> '';
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responder_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('responder_id')->constrained('responders')->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('responders')
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->orderBy('id')
            ->get(['id', 'fcm_token'])
            ->unique('fcm_token')
            ->each(fn ($responder) => DB::table('responder_push_tokens')->insert([
                'responder_id' => $responder->id,
                'token'        => $responder->fcm_token,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('responder_push_tokens');
    }
};
