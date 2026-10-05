<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDRRMC Incident Report filed by the responder when resolving an incident.
 *
 *  - report_data     : the form fields the responder filled in (JSON), kept so
 *                      the PDF can be regenerated at any time.
 *  - report_pdf_path : the generated PDF on the PRIVATE ('local') disk —
 *                      served only to logged-in admins (see
 *                      IncidentController::reportPdf()).
 *
 * No artisan? Run this in phpMyAdmin instead:
 *
 *   ALTER TABLE incidents
 *     ADD COLUMN report_data LONGTEXT NULL AFTER resolution_photo_path,
 *     ADD COLUMN report_pdf_path VARCHAR(255) NULL AFTER report_data;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->longText('report_data')->nullable()->after('resolution_photo_path');
            $table->string('report_pdf_path')->nullable()->after('report_data');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['report_data', 'report_pdf_path']);
        });
    }
};