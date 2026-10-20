<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Services\PushNotificationService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

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

        // "24 hours": every alert clears from the apps by itself then.
        $clearsAfter = Alert::clearsAfterLabel();

        return view('alerts', compact(
            'showingAlerts', 'alerts', 'titlePresets', 'customTitles', 'clearsAfter'
        ));
    }

    public function store(Request $request, PushNotificationService $push)
    {
        $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body'     => ['required', 'string'],
            'type'     => ['required', 'in:Citizens,Responders,Both'],
        ]);

        // Panel comment #22: past alerts clear themselves. Item 50: always
        // 24 hours after it's sent — the admin used to pick 1 to 30 days
        // ("Clears from the apps after").
        $alert = Alert::create([
            'title'      => $request->title,
            'subtitle'   => $request->subtitle,
            'body'       => $request->body,
            'type'       => $request->type,
            'user_id'    => auth()->id(),
            'expires_at' => now()->addHours(Alert::CLEARS_AFTER_HOURS),
        ]);

        $audience = $request->type;

        $label = match ($audience) {
            'Both'       => 'citizens and responders',
            'Responders' => 'responders',
            default      => 'citizens',
        };

        $after = Alert::clearsAfterLabel();

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
            "Broadcast sent to {$label}. It clears from the apps by itself in {$after}, on {$alert->expires_at->format('M j, g:i A')}."
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
