<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvacuationCenter;
use App\Models\Responder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvacuationCenterController extends Controller
{
    /**
     * Public — guests can view evacuation centers without logging in,
     * same as the "Evacuation Centers" tile on the app's home screen.
     */
    public function index()
    {
        return response()->json(
            EvacuationCenter::orderBy('name')->get()
        );
    }

    /**
     * Mobile-app counterpart to Admin\EvacuationCenterController::store —
     * lets an MSWD responder add a center from the app instead of only
     * through the admin panel. Writes to the exact same table, so it's
     * immediately visible everywhere (admin panel + citizen app) with no
     * separate sync step.
     *
     * Gated to authenticated Responder accounts with agency === 'MSWD'.
     * The Flutter side also hides this behind an isMswd check, but the
     * backend re-checks regardless — a token belonging to any other
     * agency (or a Citizen token) gets a 403, never silent success.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof Responder || $user->agency !== 'MSWD') {
            return response()->json([
                'message' => 'Only MSWD responders can add evacuation centers.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:255'],
            'barangay'  => ['required', 'string', 'max:255'],
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'capacity'  => ['required', 'integer', 'min:1'],
            'status'    => ['required', 'in:open,full,closed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $center = EvacuationCenter::create($validator->validated() + ['occupancy' => 0]);

        return response()->json([
            'message' => 'Evacuation center added.',
            'center'  => $center,
        ], 201);
    }
}