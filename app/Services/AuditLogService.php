<?php
// app/Services/AuditLogService.php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Responder;

class AuditLogService
{
    /**
     * $auditable is the model the action was performed on (null for
     * login/logout, which have no target record). $old/$new should be
     * plain arrays of just the changed fields — never a whole model, or
     * things like password hashes end up stored in old_values/new_values.
     *
     * user_id / user_name only ever come from the admin web login (the
     * 'web' guard). The mobile apps sign in with Sanctum tokens, and
     * inside those requests auth()->id() is the CITIZEN's or RESPONDER's
     * id — it used to be saved in user_id (a link to the admin users
     * table), so a resident's profile edit showed up as done by whichever
     * admin had the same id number, or failed when no admin did.
     */
    public static function log(
        string $action,
        string $description,
        $auditable = null,
        ?array $old = null,
        ?array $new = null
    ): void {
        $admin = auth('web')->user();

        AuditLog::create([
            'user_id'        => $admin?->id,
            'user_name'      => $admin?->name,
            'action'         => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id'   => $auditable?->id,
            'description'    => $description,
            'old_values'     => $old,
            'new_values'     => $new,
            'ip_address'     => request()->ip(),
        ]);
    }

    /**
     * Something done in the responder app — signing in or out, accepting,
     * declining or resolving an incident, MSWD evacuation-center work.
     *
     * The row is tied to the responder account (auditable = Responder,
     * no admin user), which is how the Audit Log tells responder rows
     * apart. user_name keeps a snapshot of who it was, in case the
     * account is renamed or removed later. $details goes to new_values.
     */
    public static function responder(Responder $responder, string $action, string $description, ?array $details = null): void
    {
        AuditLog::create([
            'user_id'        => null,
            'user_name'      => self::responderLabel($responder),
            'action'         => $action,
            'auditable_type' => Responder::class,
            'auditable_id'   => $responder->id,
            'description'    => $description,
            'old_values'     => null,
            'new_values'     => $details,
            'ip_address'     => request()->ip(),
        ]);
    }

    /** "Juan Dela Cruz (BFP)", or "BFP responder" when there's no name. */
    public static function responderLabel(Responder $responder): string
    {
        $name   = trim((string) $responder->full_name);
        $agency = trim((string) $responder->agency);

        if ($name === '') {
            return $agency !== '' ? "{$agency} responder" : 'Responder';
        }

        return ($agency !== '' && stripos($name, $agency) === false) ? "{$name} ({$agency})" : $name;
    }

    /**
     * How an incident is named in a log line — by what, where and when
     * instead of its ID number, e.g. "Fire report at Rizal St, Brgy.
     * Carmay East, Rosales, Pangasinan (Oct 13, 2026 9:01 AM)". Same
     * wording as Admin\IncidentController::describeIncident().
     */
    public static function describeIncident(Incident $incident): string
    {
        $what = $incident->emergency_type === 'SOS Emergency'
            ? 'SOS alert' . ($incident->sos_emergency_type ? " ({$incident->sos_emergency_type})" : '')
            : ($incident->emergency_type ?: 'incident') . ' report';
        if (! $incident->citizen_id) {
            $what = "guest {$what}";
        }

        $where = trim((string) preg_replace('/^(SOS Alert|Guest Report)\s*—\s*/u', '', (string) $incident->location));
        $when  = $incident->created_at?->format('M d, Y g:i A');

        return $what
            . ($where !== '' ? " at {$where}" : '')
            . ($when ? " ({$when})" : '');
    }
}