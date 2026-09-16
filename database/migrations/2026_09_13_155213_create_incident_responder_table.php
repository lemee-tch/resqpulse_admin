<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Switches incident <-> responder from a single "who claimed it" column
 * to a many-to-many pivot — multiple responders (backup support) can now
 * accept the same incident, instead of only the first one to tap Accept.
 *
 * The old `incidents.responder_id` / `incidents.accepted_at` columns are
 * LEFT IN PLACE (harmless, just unused going forward) rather than
 * dropped — no data migration needed since this is a fresh feature, and
 * dropping a FK column on a live table is unnecessary risk for zero
 * benefit here.
 *
 * No CLI/artisan access on this deployment — apply via phpMyAdmin SQL:
 *
 *   CREATE TABLE incident_responder (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     incident_id BIGINT UNSIGNED NOT NULL,
 *     responder_id BIGINT UNSIGNED NOT NULL,
 *     accepted_at TIMESTAMP NULL,
 *     created_at TIMESTAMP NULL,
 *     updated_at TIMESTAMP NULL,
 *     UNIQUE KEY incident_responder_unique (incident_id, responder_id),
 *     CONSTRAINT incident_responder_incident_fk
 *       FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
 *     CONSTRAINT incident_responder_responder_fk
 *       FOREIGN KEY (responder_id) REFERENCES responders(id) ON DELETE CASCADE
 *   );
 *
 * The UNIQUE(incident_id, responder_id) is what makes a second "accept"
 * tap by the SAME responder a no-op instead of a duplicate row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_responder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responder_id')->constrained()->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['incident_id', 'responder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_responder');
    }
};