<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'citizen_id', 'responder_id', 'accepted_at', 'emergency_type', 'location', 'latitude', 'longitude',
        'description', 'photo_path', 'status', 'priority', 'admin_notes',
        'resolution_notes', 'resolution_photo_path',
        'ai_detected_type', 'ai_confidence', 'ai_analysis', 'needs_review', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    protected $casts = [
        'needs_review' => 'boolean',
        'reviewed_at'  => 'datetime',
    ];

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
}