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
     *
     * Only alerts that are still showing: a past alert clears itself when
     * its time is up (expires_at, picked by the admin when sending) or
     * when an admin clears it early — see Alert::scopeShowing(). The apps
     * can also hide alerts on the phone; that's kept on the phone.
     */
    public function index(Request $request)
    {
        $audiences = $request->user() instanceof Responder
            ? ['Responders', 'Both']
            : ['Citizens', 'Both'];

        return response()->json(
            Alert::showing()
                ->whereIn('type', $audiences)
                ->orderByDesc('created_at')
                ->get()
                ->makeHidden(['user_id', 'cleared_at', 'cleared_by'])
        );
    }

    public function updateToken(Request $request)
    {
        $request->validate(['fcm_token' => ['required', 'string']]);

        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'Token saved']);
    }
}