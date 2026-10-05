<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'citizen_id', 'responder_id', 'accepted_at', 'emergency_type', 'location', 'latitude', 'longitude',
        'description', 'photo_path', 'status', 'priority', 'admin_notes',
        'resolution_notes', 'resolution_photo_path', 'report_data', 'report_pdf_path',
        'ai_detected_type', 'ai_confidence', 'ai_analysis', 'needs_review', 'reviewed_at',
        'sos_emergency_type', 'decline_reason', 'declined_at',
    ];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    protected $casts = [
        'needs_review' => 'boolean',
        'reviewed_at'  => 'datetime',
        'declined_at'  => 'datetime',
        'report_data'  => 'array',
    ];

    // Includes display_type (see getDisplayTypeAttribute() below) in
    // every JSON response automatically — both apps read incidents as
    // JSON over the API, so without this they'd each have to
    // reimplement the same "SOS Alert — {type}" formatting themselves.
    protected $appends = ['display_type'];

    // Keep the private PDF storage path out of API JSON.
    protected $hidden = ['report_pdf_path'];

    public function citizen()
    {
        return $this->belongsTo(Citizen::class);
    }

    /**
     * DEPRECATED — kept only so nothing that still reads the old
     * singular `responder_id` column breaks. Multi-responder / backup
     * support means an incident can now have several responders at
     * once, so `responders()` below is the real source of truth. Do
     * not write to `responder_id` for new code paths.
     */
    public function responder()
    {
        return $this->belongsTo(Responder::class);
    }

    /**
     * Every responder currently on this incident — the first to accept
     * AND any backup responders who joined afterward. `pivot.accepted_at`
     * records when each individual responder joined (distinct from the
     * incident's own `created_at`/`accepted_at`).
     */
    public function responders()
    {
        return $this->belongsToMany(Responder::class, 'incident_responder')
            ->withPivot('accepted_at')
            ->withTimestamps();
    }

    /**
     * Responders who have personally declined this incident — see the
     * incident_declines migration. A decline only ever means "not me":
     * it never changes the incident for anyone else, so this is a
     * separate table rather than another flag on `responders()` above,
     * which would corrupt every place that reads that relation as
     * "who's actually responding" (the "Responding Team" list, the
     * "X Responding" badge, etc. on both apps).
     *
     * IncidentController::assignedToResponder() uses this to drop an
     * incident from the declining responder's own Active Incidents feed
     * — see decline()/accept() there for how rows here are written and
     * cleared.
     */
    public function declinedBy()
    {
        return $this->belongsToMany(Responder::class, 'incident_declines')
            ->withPivot('declined_at')
            ->withTimestamps();
    }

    /**
     * "SOS Alert — Accident", "SOS Alert — Fire", etc. — what admins and
     * responders actually want to see instead of a bare "SOS Emergency"
     * label, once the citizen has picked a hazard type on the SOS screen
     * (sos_emergency_type). Falls back to the plain emergency_type for
     * everything else (regular reports already carry their real type
     * there) and to the bare label for an SOS with no type picked yet
     * (older app builds, or the citizen skipped it).
     */
    public function getDisplayTypeAttribute(): string
    {
        if ($this->emergency_type === 'SOS Emergency') {
            return $this->sos_emergency_type
                ? "SOS Alert — {$this->sos_emergency_type}"
                : 'SOS Emergency';
        }

        return (string) $this->emergency_type;
    }
}
