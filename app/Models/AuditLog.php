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
     * profile updates, an Incident for reports/SOS, etc.) — null for
     * logout and other actions with no target record. Used by
     * getActorNameAttribute() below to recover the citizen's name for
     * rows the admin guard never authenticated (see there for why).
     */
    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Display name for whoever performed this action.
     *
     * Admin actions (taken from the web admin panel) always carry
     * user_name — a snapshot taken at write time, so it survives the
     * admin account later being renamed or deleted — or failing that
     * the live user() relation. Citizen/guest actions come from the
     * mobile API, which never authenticates on the admin web guard, so
     * user_id/user_name are always null for them; the actor's name has
     * to come from the auditable record instead: the Citizen itself for
     * login()/updateProfile(), or the Incident's citizen for
     * store()/sos() (which audit the Incident, not the Citizen). A
     * guest report has no citizen at all, hence 'Guest'.
     */
    public function getActorNameAttribute(): string
    {
        if ($this->user_name || $this->user) {
            return $this->user_name ?? $this->user->name;
        }

        if ($this->auditable_type === Citizen::class) {
            return $this->auditable?->full_name ?? 'Deleted citizen';
        }

        if ($this->auditable_type === Incident::class) {
            return $this->auditable?->citizen?->full_name ?? 'Guest';
        }

        return 'System';
    }

    /**
     * true when this row was taken by an authenticated admin (web
     * guard); false for anything that came from the citizen/guest
     * mobile API instead. Powers the Actor filter and the small
     * Admin/Resident tag on the Audit Log page.
     */
    public function getIsAdminActionAttribute(): bool
    {
        return $this->user_id !== null;
    }
}