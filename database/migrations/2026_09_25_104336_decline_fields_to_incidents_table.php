<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin decline a Pending Review report instead of only ever
 * being able to approve it — e.g. a guest report that's spam, a
 * duplicate, or too vague to act on. Kept as its own pair of columns
 * rather than repurposing `status` (still just pending/responding/
 * resolved — a declined report was never worked, so "resolved" would
 * be misleading) or `admin_notes` (that's a free-form running note,
 * not a one-time reason tied to a specific action).
 *
 * needs_review is set back to false on decline (same as approve), so a
 * declined report leaves the Pending Review tab; declined_at is what
 * incident.blade.php then checks to keep it out of Active Incidents too
 * and instead show it under Resolved History with a "Declined" badge.
 *
 * No CLI/artisan access on this deployment — apply via phpMyAdmin SQL:
 *
 *   ALTER TABLE incidents
 *     ADD COLUMN decline_reason TEXT NULL AFTER reviewed_at,
 *     ADD COLUMN declined_at TIMESTAMP NULL AFTER decline_reason;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->text('decline_reason')->nullable()->after('reviewed_at');
            $table->timestamp('declined_at')->nullable()->after('decline_reason');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['decline_reason', 'declined_at']);
        });
    }
};