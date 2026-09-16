<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two fields the field-resolution flow needs (see
 * IncidentResolutionScreen in the responder app / Api\IncidentController
 * ::resolve()). Neither existed before — `admin_notes` and `photo_path`
 * are already used for other things (admin-only notes, the citizen's
 * original report photo), so resolving an incident needs its own pair
 * rather than overloading those.
 *
 * No CLI/artisan access on this deployment — apply via phpMyAdmin SQL:
 *
 *   ALTER TABLE incidents
 *     ADD COLUMN resolution_notes TEXT NULL AFTER admin_notes,
 *     ADD COLUMN resolution_photo_path TEXT NULL AFTER resolution_notes;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->text('resolution_notes')->nullable()->after('admin_notes');
            $table->text('resolution_photo_path')->nullable()->after('resolution_notes');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['resolution_notes', 'resolution_photo_path']);
        });
    }
};