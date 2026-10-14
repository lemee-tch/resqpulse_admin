<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Residents who sign up with "Mobile Number" have no email: they log in
 * with the mobile number instead. So citizens.email may now be empty.
 * It stays unique — MySQL allows any number of empty (NULL) emails.
 *
 * Runs by itself on deploy (php artisan migrate --force). The same change
 * by hand, in phpMyAdmin → SQL:
 *
 *   ALTER TABLE citizens MODIFY email VARCHAR(255) NULL;
 *
 * Running it twice is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Only possible while every resident still has an email.
        Schema::table('citizens', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};