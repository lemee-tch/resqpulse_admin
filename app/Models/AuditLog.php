<?php
// app/Models/AuditLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'action', 'auditable_type', 'auditable_id',
        'description', 'old_values', 'new_values', 'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record this action was performed on (a Citizen for login/
     * profile updates, an Incident for reports/SOS, the Responder for
     * responder-app actions, etc.) — null for logout and other actions
     * with no target record. Used below to recover who did it for rows
     * the admin login never authenticated.
     */
    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Who did it: 'admin' (web admin panel), 'responder' (responder app),
     * 'resident' or 'guest' (residents app), or 'system'.
     *
     * Admin rows carry user_id. Responder rows are tied to the Responder
     * account (AuditLogService::responder()). Resident/guest rows come
     * from the residents app and are tied to the Citizen or the Incident
     * they reported; when that incident has since been deleted, the
     * wording ("Guest …" / "Citizen …") still tells the two apart.
     */
    public function getActorTypeAttribute(): string
    {
        if ($this->user_id !== null) {
            return 'admin';
        }

        return match ($this->auditable_type) {
            Responder::class => 'responder',
            Citizen::class   => 'resident',
            Incident::class  => $this->auditable
                ? ($this->auditable->citizen_id ? 'resident' : 'guest')
                : (str_starts_with((string) $this->description, 'Guest ') ? 'guest' : 'resident'),
            default          => 'system',
        };
    }

    /**
     * Display name for whoever performed this action.
     *
     * Admin and responder rows carry user_name — a snapshot taken at
     * write time, so it survives the account later being renamed or
     * deleted — or failing that the live record. Resident/guest rows
     * have no user_name; the name comes from the Citizen itself
     * (login, profile edits) or the Incident's citizen (reports/SOS).
     * A guest's typed name is in the log wording ("Guest Juan Dela Cruz
     * (0917…) sent …").
     */
    public function getActorNameAttribute(): string
    {
        if ($this->user_name) {
            return $this->user_name;
        }
        if ($this->user_id !== null && $this->user) {
            return $this->user->name;
        }

        if ($this->auditable_type === Responder::class) {
            return $this->auditable?->full_name ?? 'Removed responder';
        }

        if ($this->auditable_type === Citizen::class) {
            return $this->auditable?->full_name ?? $this->nameFromWording() ?? 'Deleted citizen';
        }

        if ($this->auditable_type === Incident::class) {
            return $this->auditable?->citizen?->full_name
                ?? $this->nameFromWording()
                ?? ($this->auditable?->guest_name ?: 'Guest');
        }

        return 'System';
    }

    /**
     * true when this row was taken by an authenticated admin (web
     * guard); false for anything from the residents or responder apps.
     */
    public function getIsAdminActionAttribute(): bool
    {
        return $this->user_id !== null;
    }

    /** "Citizen Maria Santos reported …" / "Guest Juan Dela Cruz (0917…) sent …" → the name. */
    private function nameFromWording(): ?string
    {
        $text = (string) $this->description;

        if (preg_match('/^Guest (.+?) \(/u', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/^Citizen (.+?) (reported|sent|registered|logged|updated|verified)\b/u', $text, $m)) {
            return $m[1];
        }

        return null;
    }
}