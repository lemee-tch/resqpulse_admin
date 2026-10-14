<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assign Response Team (panel comments #6 and #13): when the admin
 * verifies a report or SOS, they choose which team(s) respond.
 *
 *  assigned_teams        the agencies sent, e.g. ["BFP","SARS"]. Only these
 *                        are notified and see the incident in the responder
 *                        app. Empty (NULL) on incidents approved before this
 *                        change — those stay visible to every team.
 *  assignment_notes      instructions for the team ("Proceed via market
 *                        road."), shown in the responder app
 *  responders_on_record  the people who went, written down for the record
 *                        (the agency accounts are shared logins)
 *  assigned_at           when the team was last set
 *  reviewed_by           the admin (users.id) who approved or declined it
 *
 * Runs by itself on deploy (php artisan migrate --force). The same change
 * by hand, in phpMyAdmin → SQL:
 *
 *   ALTER TABLE incidents
 *     ADD COLUMN assigned_teams LONGTEXT NULL AFTER priority,
 *     ADD COLUMN assignment_notes TEXT NULL AFTER assigned_teams,
 *     ADD COLUMN responders_on_record TEXT NULL AFTER assignment_notes,
 *     ADD COLUMN assigned_at TIMESTAMP NULL AFTER responders_on_record,
 *     ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER reviewed_at;
 *
 * If the SQL was already run by hand, this migration skips what exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'assigned_teams'       => fn (Blueprint $t) => $t->json('assigned_teams')->nullable()->after('priority'),
            'assignment_notes'     => fn (Blueprint $t) => $t->text('assignment_notes')->nullable()->after('assigned_teams'),
            'responders_on_record' => fn (Blueprint $t) => $t->text('responders_on_record')->nullable()->after('assignment_notes'),
            'assigned_at'          => fn (Blueprint $t) => $t->timestamp('assigned_at')->nullable()->after('responders_on_record'),
            'reviewed_by'          => fn (Blueprint $t) => $t->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at'),
        ];

        foreach ($columns as $name => $add) {
            if (! Schema::hasColumn('incidents', $name)) {
                Schema::table('incidents', fn (Blueprint $table) => $add($table));
            }
        }
    }

    public function down(): void
    {
        foreach (['reviewed_by', 'assigned_at', 'responders_on_record', 'assignment_notes', 'assigned_teams'] as $name) {
            if (Schema::hasColumn('incidents', $name)) {
                Schema::table('incidents', fn (Blueprint $table) => $table->dropColumn($name));
            }
        }
    }
};
