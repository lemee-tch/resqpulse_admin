<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Responder;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * This single endpoint serves BOTH apps (see ApiService.getAlerts()
     * comments on the Flutter side) — so which audiences it shows has to
     * depend on who's actually asking. Citizens (and anyone unrecognized)
     * get Citizens/Both; a Responder token gets Responders/Both. Before
     * this fix it was hardcoded to Citizens/Both for everyone, which
     * meant a responder-only broadcast was invisible to every responder.
     */
    public function index(Request $request)
    {
        $audiences = $request->user() instanceof Responder
            ? ['Responders', 'Both']
            : ['Citizens', 'Both'];

        return response()->json(
            Alert::whereIn('type', $audiences)
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function updateToken(Request $request)
    {
        $request->validate(['fcm_token' => ['required', 'string']]);

        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'Token saved']);
    }
}