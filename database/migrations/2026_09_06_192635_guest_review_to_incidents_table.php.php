<?php
// database/migrations/2026_09_06_000000_add_guest_review_to_incidents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false)->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('needs_review');
        });

        // Guest reports send only lat/lng — description/location must be
        // optional so validation doesn't force them to type anything.
        Schema::table('incidents', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->string('location')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['needs_review', 'reviewed_at']);
            $table->text('description')->nullable(false)->change();
            $table->string('location')->nullable(false)->change();
        });
    }
};