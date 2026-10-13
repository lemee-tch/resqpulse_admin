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
     * The tabs (?who=admin|resident|responder) narrow it to one group;
     * see AuditLog::getActorTypeAttribute() for how rows are told apart.
     */
    public function index(Request $request)
    {
        $who = in_array($request->who, ['admin', 'resident', 'responder'], true) ? $request->who : null;

        // Every filter except the tab, so the tab counts follow them too.
        $filtered = AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
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

        $logs = self::onlyWho(clone $filtered, $who)
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

        return view('audit-log', compact('logs', 'adminUsers', 'actions', 'counts', 'who'));
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