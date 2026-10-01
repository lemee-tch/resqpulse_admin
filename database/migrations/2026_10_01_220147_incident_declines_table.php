<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (incident, responder) that this responder has personally
 * declined. Kept as its own table rather than reusing the
 * incident_responder pivot (which means "joined/accepted") — a decline
 * is the opposite of that, and mixing the two into one table would
 * corrupt every place that reads incident_responder as "who's actually
 * responding" (the "Responding Team" list and the "X Responding" badge
 * on both apps, Report History's resolvedForResponder() query, etc.).
 *
 * Declining only ever means "not me, personally" — the incident stays
 * fully visible and actionable for everyone else (same agency or a
 * different one). IncidentController::assignedToResponder() excludes an
 * incident from a responder's own feed only when a row here matches
 * BOTH that incident and that responder's own id; accept() clears the
 * row again if the same responder later changes their mind and joins.
 *
 * No CLI/artisan access on this deployment — apply via phpMyAdmin SQL:
 *
 *   CREATE TABLE incident_declines (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     incident_id BIGINT UNSIGNED NOT NULL,
 *     responder_id BIGINT UNSIGNED NOT NULL,
 *     declined_at TIMESTAMP NULL,
 *     created_at TIMESTAMP NULL,
 *     updated_at TIMESTAMP NULL,
 *     UNIQUE KEY incident_declines_unique (incident_id, responder_id),
 *     CONSTRAINT incident_declines_incident_fk
 *       FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
 *     CONSTRAINT incident_declines_responder_fk
 *       FOREIGN KEY (responder_id) REFERENCES responders(id) ON DELETE CASCADE
 *   );
 *
 * The UNIQUE(incident_id, responder_id) is what makes declining twice
 * (a stale UI retry, etc.) a safe no-op instead of a duplicate row —
 * see IncidentController::decline()'s syncWithoutDetaching() call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_declines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responder_id')->constrained()->cascadeOnDelete();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->unique(['incident_id', 'responder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_declines');
    }
};