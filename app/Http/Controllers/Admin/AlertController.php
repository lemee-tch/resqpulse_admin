<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Services\PushNotificationService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index()
    {
        $alerts = Alert::orderByDesc('created_at')->get();

        $recentAlerts = Alert::where('created_at', '>=', now()->subDays(3))
            ->orderByDesc('created_at')
            ->get();

        return view('alerts', compact('alerts', 'recentAlerts'));
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

        AuditLogService::log('created', "Sent broadcast \"{$alert->title}\" to {$label}.", $alert);

        $audience = $request->type;

        if (in_array($audience, ['Citizens', 'Both'], true)) {
            $push->broadcastToAllCitizens($alert->title, $alert->body);
        }

        if (in_array($audience, ['Responders', 'Both'], true)) {
            $push->broadcastToAllResponders($alert->title, $alert->body);
        }

        $label = match ($audience) {
            'Both'       => 'citizens and responders',
            'Responders' => 'responders',
            default      => 'citizens',
        };

        return back()->with('success', "Broadcast sent to {$label}.");
    }
}