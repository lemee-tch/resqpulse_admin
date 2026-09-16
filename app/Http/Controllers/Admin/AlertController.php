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

    public function index()
    {
        $alerts = Alert::orderByDesc('created_at')->get();

        $recentAlerts = Alert::where('created_at', '>=', now()->subDays(3))
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

        return view('alerts', compact('alerts', 'recentAlerts', 'titlePresets', 'customTitles'));
    }

    public function store(Request $request, PushNotificationService $push)
    {
        $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body'     => ['required', 'string'],
            'type'     => ['required', 'in:Citizens,Responders,Both'],
        ]);

        $alert = Alert::create([
            'title'    => $request->title,
            'subtitle' => $request->subtitle,
            'body'     => $request->body,
            'type'     => $request->type,
            'user_id'  => auth()->id(),
        ]);

        $audience = $request->type;

        $label = match ($audience) {
            'Both'       => 'citizens and responders',
            'Responders' => 'responders',
            default      => 'citizens',
        };

        AuditLogService::log('created', "Sent broadcast \"{$alert->title}\" to {$label}.", $alert);

        if (in_array($audience, ['Citizens', 'Both'], true)) {
            $push->broadcastToAllCitizens($alert->title, $alert->body);
        }

        if (in_array($audience, ['Responders', 'Both'], true)) {
            $push->broadcastToAllResponders($alert->title, $alert->body);
        }

        return back()->with('success', "Broadcast sent to {$label}.");
    }
}