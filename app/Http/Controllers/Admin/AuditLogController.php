<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Responder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * One Audit Log for everyone: admin actions (web panel), residents
     * and guests (residents app: registration, logins, profile edits,
     * reports, SOS) and responders (responder app: logins, accepting,
     * declining and resolving incidents, MSWD evacuation-center work).
     *
     * The "who" dropdown narrows it to one group or one admin:
     *
     *   ?who=admin | resident | responder   one group
     *   ?who=admin-3                        one admin (users.id 3)
     *
     * Older links with ?user_id=3 (the old "Any Admin" dropdown) still
     * work. See AuditLog::getActorTypeAttribute() for how rows are told
     * apart.
     */
    public function index(Request $request)
    {
        $whoParam = (string) $request->input('who', '');
        $adminId  = null;
        if (preg_match('/^admin-(\d+)$/', $whoParam, $m)) {
            $who     = 'admin';
            $adminId = (int) $m[1];
        } else {
            $who = in_array($whoParam, ['admin', 'resident', 'responder'], true) ? $whoParam : null;
        }
        if ($adminId === null && $request->filled('user_id') && in_array($who, [null, 'admin'], true)) {
            $who     = 'admin';
            $adminId = (int) $request->user_id;
        }

        // Every filter except who, so the counts in the dropdown follow
        // them too.
        $filtered = AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->date))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where(fn ($w) => $w->where('description', 'like', $term)->orWhere('user_name', 'like', $term));
            });

        $counts = [
            'all'       => (clone $filtered)->count(),
            'admin'     => self::onlyWho(clone $filtered, 'admin')->count(),
            'resident'  => self::onlyWho(clone $filtered, 'resident')->count(),
            'responder' => self::onlyWho(clone $filtered, 'responder')->count(),
        ];
        // Per admin, for the admins listed under Admin.
        $adminCounts = (clone $filtered)->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as total')->groupBy('user_id')
            ->pluck('total', 'user_id');

        $logs = self::onlyWho(clone $filtered, $who)
            ->when($adminId !== null, fn ($q) => $q->where('user_id', $adminId))
            ->with([
                'user',
                // A report's citizen gives the resident's name.
                'auditable' => fn (MorphTo $morphTo) => $morphTo->morphWith([Incident::class => ['citizen']]),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $adminUsers = User::orderBy('name')->get(['id', 'name']);
        // Only actions that actually appear in the log.
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');

        // What the dropdown shows as picked.
        $whoValue = $adminId !== null ? "admin-{$adminId}" : ($who ?? '');

        return view('audit-log', compact('logs', 'adminUsers', 'actions', 'counts', 'adminCounts', 'who', 'whoValue'));
    }

    /** Narrows the query to one group of people (null = everyone). */
    private static function onlyWho(Builder $query, ?string $who): Builder
    {
        return match ($who) {
            'admin'     => $query->whereNotNull('user_id'),
            'responder' => $query->whereNull('user_id')->where('auditable_type', Responder::class),
            'resident'  => $query->whereNull('user_id')->where(fn ($q) => $q
                ->whereNull('auditable_type')
                ->orWhere('auditable_type', '!=', Responder::class)),
            default     => $query,
        };
    }
}