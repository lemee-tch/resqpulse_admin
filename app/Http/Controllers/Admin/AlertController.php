<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Services\PushNotificationService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertController extends Controller
{
    /**
     * The curated preset list lives here now — single source of truth,
     * instead of being duplicated inline in alerts.blade.php's own @php
     * block (which is where it used to live). Kept as its own method so
     * both index() and the custom-title exclusion below reference the
     * exact same list, rather than two copies quietly drifting apart.
     */
    private function titlePresets(): array
    {
        return [
            'Typhoon Warning',
            'Heavy Rainfall Advisory',
            'Flood Warning',
            'Earthquake Advisory',
            'Fire Warning',
            'Landslide Warning',
            'Heat Index Advisory',
            'Water Service Interruption',
            'Power Interruption Notice',
            'Road Closure Advisory',
            'Evacuation Order',
            'All Clear / Warning Lifted',
        ];
    }

    /**
     * Active Alerts: what the apps show right now (Alert::scopeShowing()),
     * each with when it clears and a Clear button. Broadcast History:
     * every broadcast ever sent, cleared ones included.
     */
    public function index()
    {
        $showingAlerts = Alert::showing()
            ->orderByDesc('created_at')
            ->get();

        $alerts = Alert::with(['user', 'clearedBy'])
            ->orderByDesc('created_at')
            ->get();

        $titlePresets = $this->titlePresets();

        // Whatever an admin has typed into "Other (custom title)" on a
        // past alert becomes a pickable option going forward — every
        // title ever sent is already sitting in the alerts table, so
        // this just surfaces titles that AREN'T already one of the
        // built-in presets, rather than needing a separate table to
        // track them.
        $customTitles = Alert::select('title')
            ->distinct()
            ->whereNotIn('title', $titlePresets)
            ->orderBy('title')
            ->pluck('title');

        $clearAfterDays = Alert::CLEAR_AFTER_DAYS;
        $defaultClearAfter = Alert::DEFAULT_CLEAR_AFTER_DAYS;

        return view('alerts', compact(
            'showingAlerts', 'alerts', 'titlePresets', 'customTitles', 'clearAfterDays', 'defaultClearAfter'
        ));
    }

    public function store(Request $request, PushNotificationService $push)
    {
        $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'subtitle'    => ['nullable', 'string', 'max:255'],
            'body'        => ['required', 'string'],
            'type'        => ['required', 'in:Citizens,Responders,Both'],
            // Panel comment #22: past alerts clear themselves.
            'clear_after' => ['nullable', 'integer', Rule::in(Alert::CLEAR_AFTER_DAYS)],
        ], [
            'clear_after.in' => 'Choose when the alert clears from the apps.',
        ]);

        $days = (int) ($request->input('clear_after') ?: Alert::DEFAULT_CLEAR_AFTER_DAYS);

        $alert = Alert::create([
            'title'      => $request->title,
            'subtitle'   => $request->subtitle,
            'body'       => $request->body,
            'type'       => $request->type,
            'user_id'    => auth()->id(),
            'expires_at' => now()->addDays($days),
        ]);

        $audience = $request->type;

        $label = match ($audience) {
            'Both'       => 'citizens and responders',
            'Responders' => 'responders',
            default      => 'citizens',
        };

        $after = $days === 1 ? '1 day' : "{$days} days";

        AuditLogService::log(
            'created',
            "Sent broadcast \"{$alert->title}\" to {$label} — clears from the apps after {$after}.",
            $alert
        );

        if (in_array($audience, ['Citizens', 'Both'], true)) {
            $push->broadcastToAllCitizens($alert->title, $alert->body);
        }

        if (in_array($audience, ['Responders', 'Both'], true)) {
            $push->broadcastToAllResponders($alert->title, $alert->body);
        }

        return back()->with(
            'success',
            "Broadcast sent to {$label}. It clears from the apps on {$alert->expires_at->format('M j, g:i A')}."
        );
    }

    /**
     * Clears one alert from the apps now, before its time is up. It stays
     * in Broadcast History, marked "Cleared by <admin>".
     */
    public function clear(Alert $alert)
    {
        if (! $alert->isShowing()) {
            return back()->with('success', "\"{$alert->title}\" was already cleared from the apps.");
        }

        $alert->update(['cleared_at' => now(), 'cleared_by' => auth('web')->id()]);

        AuditLogService::log('updated', "Cleared broadcast \"{$alert->title}\" from the apps.", $alert);

        return back()->with('success', "\"{$alert->title}\" was cleared. It no longer shows in {$alert->appsLabel()}.");
    }

    /** Active Alerts → Clear all: every alert the apps still show. */
    public function clearAll()
    {
        $alerts = Alert::showing()->get();

        if ($alerts->isEmpty()) {
            return back()->with('success', 'There are no alerts in the apps to clear.');
        }

        Alert::whereKey($alerts->modelKeys())->update([
            'cleared_at' => now(),
            'cleared_by' => auth('web')->id(),
        ]);

        $count = $alerts->count();
        $what  = $count === 1 ? '1 broadcast' : "{$count} broadcasts";

        AuditLogService::log(
            'updated',
            "Cleared {$what} from the apps: " . $alerts->pluck('title')->map(fn ($t) => "\"{$t}\"")->implode(', ') . '.'
        );

        return back()->with('success', "Cleared {$what}. The apps no longer show " . ($count === 1 ? 'it.' : 'them.'));
    }

    /**
     * Broadcast History → Delete: removes a broadcast that's no longer in
     * the apps for good (e.g. a test). One still showing is cleared first.
     */
    public function destroy(Alert $alert)
    {
        if ($alert->isShowing()) {
            return back()->with('error', "\"{$alert->title}\" is still in the apps. Clear it first.");
        }

        $title = $alert->title;
        $alert->delete();

        AuditLogService::log('deleted', "Deleted broadcast \"{$title}\" from Broadcast History.");

        return back()->with('success', "\"{$title}\" was deleted from Broadcast History.");
    }
}
