<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Remember me" on the admin login relies on Auth::attempt() writing a
 * token to users.remember_token. If that column is missing (e.g. it was
 * trimmed from a customized users migration), Auth::attempt() still
 * succeeds but silently can't persist the remember cookie — the person
 * gets logged out the moment their session expires, checkbox or not.
 * This migration guarantees the column exists without clobbering it if
 * it's already there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->rememberToken();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left as a no-op — dropping remember_token on
        // rollback isn't safe to assume this migration was the one
        // that added it.
    }
};