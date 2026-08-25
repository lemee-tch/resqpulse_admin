<?php
// app/Services/AuditLogService.php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    /**
     * $auditable is the model the action was performed on (null for
     * login/logout, which have no target record). $old/$new should be
     * plain arrays of just the changed fields — never a whole model, or
     * things like password hashes end up stored in old_values/new_values.
     */
    public static function log(
        string $action,
        string $description,
        $auditable = null,
        ?array $old = null,
        ?array $new = null
    ): void {
        AuditLog::create([
            'user_id'        => auth()->id(),
            'user_name'      => auth()->user()?->name,
            'action'         => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id'   => $auditable?->id,
            'description'    => $description,
            'old_values'     => $old,
            'new_values'     => $new,
            'ip_address'     => request()->ip(),
        ]);
    }
}