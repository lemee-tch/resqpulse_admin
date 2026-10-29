<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blocking residents (item 54). Reject removes a sign-up whose ID can't be
 * accepted, and the resident may sign up again. Block is for fake or prank
 * accounts: the account is kept but locked.
 *
 *  blocked_at    when MDRRMO blocked it (null = not blocked)
 *  blocked_by    the admin who blocked it (users.id)
 *  block_reason  why — the resident sees it when the app refuses them
 *
 * A blocked resident can't log in, send a report or an SOS (not even as a
 * guest with the same mobile number), or sign up again with the same email
 * or mobile number. Residents Verification → Blocked → Unblock gives it
 * all back.
 *
 * Runs by itself on deploy (php artisan migrate --force). The same change
 * by hand, in phpMyAdmin → SQL:
 *
 *   ALTER TABLE citizens
 *     ADD COLUMN blocked_at TIMESTAMP NULL,
 *     ADD COLUMN blocked_by BIGINT UNSIGNED NULL,
 *     ADD COLUMN block_reason VARCHAR(255) NULL;
 *
 * If the SQL was already run by hand, this migration skips what exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'blocked_at'   => fn (Blueprint $t) => $t->timestamp('blocked_at')->nullable(),
            'blocked_by'   => fn (Blueprint $t) => $t->unsignedBigInteger('blocked_by')->nullable(),
            'block_reason' => fn (Blueprint $t) => $t->string('block_reason', 255)->nullable(),
        ];

        foreach ($columns as $name => $add) {
            if (! Schema::hasColumn('citizens', $name)) {
                Schema::table('citizens', fn (Blueprint $table) => $add($table));
            }
        }
    }

    public function down(): void
    {
        foreach (['block_reason', 'blocked_by', 'blocked_at'] as $name) {
            if (Schema::hasColumn('citizens', $name)) {
                Schema::table('citizens', fn (Blueprint $table) => $table->dropColumn($name));
            }
        }
    }
};
