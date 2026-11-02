<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Backup for a responding team (item 55) — see the create_backup_requests
 * migration.
 *
 *   waiting   the team on scene asked in Resqponder (Request Backup) and
 *             MDRRMO hasn't answered yet
 *   sent      MDRRMO sent the teams in sent_teams (Incident Details → Send
 *             Backup) — on the team's request, or on its own
 *   declined  MDRRMO can't send backup; reply says why
 *
 * Resqponder gets every request with the incident (backup_requests), with
 * who asked in words (requested_by).
 */
class BackupRequest extends Model
{
    public const WAITING  = 'waiting';
    public const SENT     = 'sent';
    public const DECLINED = 'declined';

    protected $fillable = [
        'incident_id', 'responder_id', 'teams', 'needs', 'status',
        'sent_teams', 'added_teams', 'reply', 'handled_by', 'handled_at',
    ];

    protected $casts = [
        'teams'       => 'array',
        'sent_teams'  => 'array',
        'added_teams' => 'array',
        'handled_at'  => 'datetime',
    ];

    protected $appends = ['requested_by'];

    // Who in MDRRMO answered stays on the admin pages.
    protected $hidden = ['handled_by', 'added_teams', 'responder', 'handledBy', 'incident'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** The responder who asked (none when MDRRMO sent backup on its own). */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class);
    }

    /** The admin who sent the backup or answered. */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isWaiting(): bool
    {
        return $this->status === self::WAITING;
    }

    public function isSent(): bool
    {
        return $this->status === self::SENT;
    }

    /** The team on scene asked for it (not MDRRMO on its own). */
    public function askedByTeam(): bool
    {
        return $this->responder_id !== null;
    }

    /** The teams asked for, in the order of Incident::TEAMS. */
    public function teamCodes(): array
    {
        return self::ordered($this->teams);
    }

    /** The teams MDRRMO sent, in the order of Incident::TEAMS. */
    public function sentTeamCodes(): array
    {
        return self::ordered($this->sent_teams);
    }

    /** Sent teams the incident wasn't sent to before — added then. */
    public function addedTeamCodes(): array
    {
        return self::ordered($this->added_teams);
    }

    /** "Juan Dela Cruz (BFP)" — or null when MDRRMO sent backup on its own. */
    public function getRequestedByAttribute(): ?string
    {
        if (! $this->responder_id) {
            return null;
        }

        return $this->responder ? self::label($this->responder) : 'A responder';
    }

    /** "Juan Dela Cruz (BFP)". */
    public static function label(Responder $responder): string
    {
        $name = trim((string) $responder->full_name);

        return ($name !== '' ? $name : 'Responder') . ($responder->agency ? " ({$responder->agency})" : '');
    }

    /** "BFP", "BFP and SARS", "BFP, PNP and SARS". */
    public static function teamList(array $teams): string
    {
        if (count($teams) <= 1) {
            return $teams[0] ?? 'no team';
        }

        return implode(', ', array_slice($teams, 0, -1)) . ' and ' . end($teams);
    }

    private static function ordered($teams): array
    {
        return array_values(array_intersect(array_keys(Incident::TEAMS), array_unique((array) $teams)));
    }
}
