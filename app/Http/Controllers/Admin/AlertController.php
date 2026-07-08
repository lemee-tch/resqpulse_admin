<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index()
    {
        $alerts = Alert::orderByDesc('created_at')->get();
        return view('alerts', compact('alerts'));
    }

    public function store(Request $request, PushNotificationService $push)
    {
        $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body'     => ['required', 'string'],
            'type'     => ['required', 'in:Alerts,Updates'],
        ]);

        $alert = Alert::create([
            'title'    => $request->title,
            'subtitle' => $request->subtitle,
            'body'     => $request->body,
            'type'     => $request->type,
            'user_id'  => auth()->id(),
        ]);

        $push->broadcastToAllCitizens($alert->title, $alert->body);

        return back()->with('success', 'Broadcast sent to all citizens.');
    }
}