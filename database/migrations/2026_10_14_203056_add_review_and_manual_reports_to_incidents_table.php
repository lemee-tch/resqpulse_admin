<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Incidents page now has four tabs: Pending → Active → For Review →
 * Resolved.
 *
 *  status 'for_review'   new. A responder sent the Final MDRRMC report; the
 *                        admin checks it and either marks the incident
 *                        resolved or sends the report back to the team.
 *
 *  report_submitted_at   when the Final report reached For Review
 *  report_returned_at    when MDRRMO last sent the report back
 *  report_return_reason  what MDRRMO asked the team to fix
 *  resolved_at           when the incident was marked resolved
 *  resolved_by           the admin (users.id) who marked it resolved or
 *                        added it with "Add Resolved Report"
 *  source                'app' (reported through ResQPulse) or 'manual'
 *                        (an incident handled outside the app that MDRRMO
 *                        added on the Resolved tab)
 *  reported_via          manual records only: Walk-in, Phone call, Radio…
 *
 * Runs by itself on deploy (php artisan migrate --force). The same change
 * by hand, in phpMyAdmin → SQL:
 *
 *   ALTER TABLE incidents
 *     MODIFY status ENUM('pending','responding','for_review','resolved') NOT NULL DEFAULT 'pending',
 *     ADD COLUMN report_submitted_at TIMESTAMP NULL AFTER report_pdf_path,
 *     ADD COLUMN report_returned_at TIMESTAMP NULL AFTER report_submitted_at,
 *     ADD COLUMN report_return_reason TEXT NULL AFTER report_returned_at,
 *     ADD COLUMN resolved_at TIMESTAMP NULL AFTER report_return_reason,
 *     ADD COLUMN resolved_by BIGINT UNSIGNED NULL AFTER resolved_at,
 *     ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'app' AFTER resolved_by,
 *     ADD COLUMN reported_via VARCHAR(50) NULL AFTER source;
 *   UPDATE incidents SET resolved_at = updated_at
 *     WHERE status = 'resolved' AND resolved_at IS NULL;
 *
 * If the SQL was already run by hand, this migration skips what exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('status', ['pending', 'responding', 'for_review', 'resolved'])
                ->default('pending')
                ->change();
        });

        $columns = [
            'report_submitted_at'  => fn (Blueprint $t) => $t->timestamp('report_submitted_at')->nullable()->after('report_pdf_path'),
            'report_returned_at'   => fn (Blueprint $t) => $t->timestamp('report_returned_at')->nullable()->after('report_submitted_at'),
            'report_return_reason' => fn (Blueprint $t) => $t->text('report_return_reason')->nullable()->after('report_returned_at'),
            'resolved_at'          => fn (Blueprint $t) => $t->timestamp('resolved_at')->nullable()->after('report_return_reason'),
            'resolved_by'          => fn (Blueprint $t) => $t->unsignedBigInteger('resolved_by')->nullable()->after('resolved_at'),
            'source'               => fn (Blueprint $t) => $t->string('source', 20)->default('app')->after('resolved_by'),
            'reported_via'         => fn (Blueprint $t) => $t->string('reported_via', 50)->nullable()->after('source'),
        ];

        foreach ($columns as $name => $add) {
            if (! Schema::hasColumn('incidents', $name)) {
                Schema::table('incidents', fn (Blueprint $table) => $add($table));
            }
        }

        // Incidents resolved before this change: their last update is the
        // closest thing to when they were resolved.
        DB::table('incidents')
            ->where('status', 'resolved')
            ->whereNull('resolved_at')
            ->update(['resolved_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        // Anything still waiting for review goes back to the team.
        DB::table('incidents')->where('status', 'for_review')->update(['status' => 'responding']);

        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('status', ['pending', 'responding', 'resolved'])
                ->default('pending')
                ->change();
        });

        foreach (['reported_via', 'source', 'resolved_by', 'resolved_at', 'report_return_reason', 'report_returned_at', 'report_submitted_at'] as $name) {
            if (Schema::hasColumn('incidents', $name)) {
                Schema::table('incidents', fn (Blueprint $table) => $table->dropColumn($name));
            }
        }
    }
};
