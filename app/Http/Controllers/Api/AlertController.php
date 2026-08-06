<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Citizens should only see broadcasts targeted at 'Citizens' or
     * 'Both' — not the ones sent to responders only.
     */
    public function index()
    {
        return response()->json(
            Alert::whereIn('type', ['Citizens', 'Both'])
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