<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a citizen pick a hazard type (Fire, Flood, Accident, etc.) on the
 * SOS screen before holding the button — see sos.dart / api_service.dart
 * sendSOS() and Api\IncidentController::sos(). Kept as its own column,
 * separate from:
 *   - emergency_type, which stays hard-coded 'SOS Emergency' for every
 *     SOS row — every SOS-specific query/filter/route in the codebase
 *     relies on that exact string, so changing it would break them all.
 *   - ai_detected_type, which is the AI's own guess from the photo (now
 *     guided by this value as a hint, but not overwritten by it — see
 *     ImageAnalysisService::classify()).
 *
 * No CLI/artisan access on this deployment — apply via phpMyAdmin SQL:
 *
 *   ALTER TABLE incidents
 *     ADD COLUMN sos_emergency_type VARCHAR(255) NULL AFTER emergency_type;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('sos_emergency_type')->nullable()->after('emergency_type');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn('sos_emergency_type');
        });
    }
};