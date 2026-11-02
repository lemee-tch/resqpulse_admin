<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvacuationCenter;
use App\Models\Responder;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvacuationCenterController extends Controller
{
    /**
     * Public — guests can view evacuation centers without logging in,
     * same as the "Evacuation Centers" tile on the app's home screen.
     */
    /**
     * Public — guests can view evacuation centers without logging in,
     * same as the "Evacuation Centers" tile on the app's home screen.
     * withCount('evacuees') adds an evacuees_count column to each
     * center in the response — how many residents have been logged
     * there, without a separate request per center.
     */
    public function index()
    {
        return response()->json(
            EvacuationCenter::withCount('evacuees')->orderBy('name')->get()
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
            // Optional now — matches the admin web panel's Add Center
            // form, which never asked for capacity either. The column
            // already defaults to 0 in the migration, so a center added
            // from the field without a capacity figure is safe; an
            // admin can set the real number later from the web panel
            // once it's actually known.
            'capacity'  => ['nullable', 'integer', 'min:1'],
            // Centers are always open (item 58): a new one has room. The
            // app still sends 'open'; 'full' is set later from the list.
            'status'    => ['nullable', 'in:open,full'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $center = EvacuationCenter::create([
            'status'    => $request->input('status') ?: EvacuationCenter::HAS_ROOM,
            'occupancy' => 0,
            'capacity'  => $request->capacity ?? 0,
        ] + $validator->validated());

        AuditLogService::responder($user, 'created', "Added evacuation center {$center->name} (Brgy. {$center->barangay}).");

        return response()->json([
            'message' => 'Evacuation center added.',
            'center'  => $center,
        ], 201);
    }

    /**
     * MSWD marks a center full once it fills up (Resqponder → Evacuation
     * Centers → Mark Full), and takes that off when it has room again
     * ('open'). Centers are always open (item 58): there's no "closed".
     * Same MSWD-only gate as store().
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
            'status' => ['required', 'in:open,full'],
        ], [
            'status.in' => 'A center can only be marked full, or as having room again.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $oldStatus = $center->status;
        $center->update(['status' => $request->status]);

        if ($oldStatus !== $center->status) {
            AuditLogService::responder(
                $user,
                'updated',
                $center->isFull()
                    ? "Marked evacuation center {$center->name} as full."
                    : "Marked evacuation center {$center->name} as having room again.",
                ['status' => $center->status, 'was' => $oldStatus]
            );
        }

        return response()->json([
            'message' => $center->isFull() ? "{$center->name} is marked full." : "{$center->name} has room again.",
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

        // A center that's full shouldn't take new arrivals (centers are
        // always open — item 58). The Flutter screen already blocks this
        // client-side (the "Log Evacuee" button is disabled and the form
        // itself shows a blocking message instead of the form), but that's
        // just a convenience for a responder looking at a fresh list —
        // this is the actual enforcement, so a stale cached screen, a
        // center marked full while the form was up, or a direct API call
        // can't bypass it.
        if ($center->isFull()) {
            return response()->json([
                'message' => "{$center->name} is full and can't accept new evacuees.",
            ], 422);
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

        $evacueeName = trim("{$evacuee->first_name} {$evacuee->last_name}");
        AuditLogService::responder($user, 'created', "Logged evacuee {$evacueeName} at {$center->name}.");

        return response()->json([
            'message' => 'Evacuee logged.',
            'evacuee' => $evacuee,
        ], 201);
    }

    /**
     * All evacuees logged across every center, newest first — the
     * mobile counterpart to Admin\EvacuationCenterController::showLog,
     * which only shows one center's log at a time. This is the "bird's
     * eye" view for the Resident Logs quick-access screen. Same
     * MSWD-only gate as logging itself — viewing isn't opened up to
     * every agency, since this is MSWD's own field data.
     */
    public function evacuees(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof Responder || $user->agency !== 'MSWD') {
            return response()->json([
                'message' => 'Only MSWD responders can view the evacuee log.',
            ], 403);
        }

        $evacuees = \App\Models\Evacuee::with('evacuationCenter:id,name,barangay')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($evacuees);
    }
}