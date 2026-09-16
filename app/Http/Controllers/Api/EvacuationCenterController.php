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

    /**
     * The Add Center form no longer asks for a status up front — every
     * new center simply starts 'open' (set server-side, see store()
     * above's validator still requiring the Flutter side to send it as
     * 'open'). Changing it afterward is this action instead, e.g. an
     * MSWD responder marking a center 'full' once it fills up or
     * 'closed' when it's no longer in use. Same MSWD-only gate as store().
     */
    public function updateStatus(Request $request, EvacuationCenter $center)
    {
        $user = $request->user();

        if (! $user instanceof Responder || $user->agency !== 'MSWD') {
            return response()->json([
                'message' => 'Only MSWD responders can update evacuation centers.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:open,full,closed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $center->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status updated.',
            'center'  => $center,
        ]);
    }

    /**
     * MSWD responders log evacuees in the field, one row per person —
     * arrival only, no check-out step. Same MSWD-only gate as store()
     * and updateStatus() above. The web admin panel can only VIEW this
     * log (Admin\EvacuationCenterController::showLog); creating an
     * entry happens exclusively here, from the responder app.
     */
    public function storeEvacuee(Request $request, EvacuationCenter $center)
    {
        $user = $request->user();

        if (! $user instanceof Responder || $user->agency !== 'MSWD') {
            return response()->json([
                'message' => 'Only MSWD responders can log evacuees.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name'     => ['required', 'string', 'max:255'],
            'middle_name'    => ['nullable', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'suffix'         => ['nullable', 'string', 'max:20'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'barangay'       => ['required', 'string', 'max:255'],
            'gender'         => ['required', 'in:Male,Female'],
            'age'            => ['required', 'integer', 'min:0', 'max:120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $evacuee = $center->evacuees()->create($validator->validated() + ['logged_by' => $user->id]);

        return response()->json([
            'message' => 'Evacuee logged.',
            'evacuee' => $evacuee,
        ], 201);
    }
}