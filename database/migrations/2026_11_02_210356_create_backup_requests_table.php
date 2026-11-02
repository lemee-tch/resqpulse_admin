<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backup for a responding team (item 55). One row per backup request:
 *  - the team on scene asks in Resqponder (Request Backup): responder_id
 *    is who asked, and it waits for MDRRMO's answer; or
 *  - MDRRMO sends backup on its own from Incident Details (Send Backup):
 *    no responder_id, and it's "sent" right away.
 *
 *  teams        the teams asked for: BFP / PNP / SARS / HCU
 *  needs        what's needed, e.g. "Two people trapped — rescue tools"
 *  status       waiting | sent | declined (MDRRMO can't send backup)
 *  sent_teams   the teams MDRRMO sent
 *  added_teams  the ones the incident wasn't sent to before (added then)
 *  reply        MDRRMO's answer when it can't send backup
 *  handled_by   the admin who sent it or answered (users.id)
 *  handled_at   when
 *
 * Runs by itself on deploy (php artisan migrate --force). The same table by
 * hand, in phpMyAdmin → SQL:
 *
 *   CREATE TABLE backup_requests (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     incident_id BIGINT UNSIGNED NOT NULL,
 *     responder_id BIGINT UNSIGNED NULL,
 *     teams JSON NULL,
 *     needs VARCHAR(255) NULL,
 *     status VARCHAR(20) NOT NULL DEFAULT 'waiting',
 *     sent_teams JSON NULL,
 *     added_teams JSON NULL,
 *     reply VARCHAR(255) NULL,
 *     handled_by BIGINT UNSIGNED NULL,
 *     handled_at TIMESTAMP NULL,
 *     created_at TIMESTAMP NULL,
 *     updated_at TIMESTAMP NULL,
 *     INDEX backup_requests_incident_id_status_index (incident_id, status),
 *     FOREIGN KEY (incident_id) REFERENCES incidents (id) ON DELETE CASCADE,
 *     FOREIGN KEY (responder_id) REFERENCES responders (id) ON DELETE SET NULL,
 *     FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
 *   );
 *
 * If the table was already made by hand, this migration skips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backup_requests')) {
            return;
        }

        Schema::create('backup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responder_id')->nullable()->constrained()->nullOnDelete();
            $table->json('teams')->nullable();
            $table->string('needs', 255)->nullable();
            $table->string('status', 20)->default('waiting');
            $table->json('sent_teams')->nullable();
            $table->json('added_teams')->nullable();
            $table->string('reply', 255)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['incident_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_requests');
    }
};
