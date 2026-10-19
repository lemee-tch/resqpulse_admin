<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Responder;
use App\Models\ResponderPushToken;
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

    /**
     * The phone's push token, sent by both apps after signing in and on
     * every start. A resident has one phone per account. A responder
     * account is shared by a whole team, so each phone signed in to it is
     * kept (ResponderPushToken) — before, the last phone to open the app
     * replaced the others and was the only one that got incident alerts.
     */
    public function updateToken(Request $request)
    {
        $request->validate(['fcm_token' => ['required', 'string', 'max:512']]);

        $user  = $request->user();
        $token = $request->fcm_token;

        $user->update(['fcm_token' => $token]);

        if ($user instanceof Responder) {
            // This phone now belongs to this account. If it was signed in
            // to another team's account before, it stops getting that
            // team's alerts.
            ResponderPushToken::updateOrCreate(['token' => $token], ['responder_id' => $user->id]);
            Responder::where('fcm_token', $token)->whereKeyNot($user->id)->update(['fcm_token' => null]);
        }

        return response()->json(['message' => 'Token saved']);
    }
}