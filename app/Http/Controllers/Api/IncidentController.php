<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Models\Incident;
use App\Models\Responder;
use App\Services\BarangayLocationService;
use App\Services\ImageAnalysisService;
use App\Services\PushNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class IncidentController extends Controller
{
    /**
     * Resolves the citizen from the bearer token if one was sent, WITHOUT
     * rejecting the request when it's absent or invalid. This is what
     * lets store()/sos() serve both logged-in citizens and guests from
     * the exact same endpoint — see api.php, where these two routes were
     * deliberately pulled out of the auth:sanctum group.
     */
    private function resolveOptionalCitizen(Request $request): ?Citizen
    {
        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken?->tokenable instanceof Citizen ? $accessToken->tokenable : null;
    }

    public function store(
        Request $request,
        ImageAnalysisService $imageAnalysis,
        PushNotificationService $push,
        BarangayLocationService $locationService
    ) {
        $citizen = $this->resolveOptionalCitizen($request);
        $isGuest = ! $citizen;

        $validator = Validator::make($request->all(), [
            'emergency_type' => ['required', 'string', 'max:255'],
            // Guests only ever send a GPS pin — location text and a
            // description are nice-to-have, not required, for them.
            'location'       => [$isGuest ? 'nullable' : 'required', 'string', 'max:255'],
            'description'    => [$isGuest ? 'nullable' : 'required', 'string'],
            'latitude'       => [$isGuest ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'longitude'      => [$isGuest ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
            'photos'         => [$isGuest ? 'nullable' : 'required', 'array', $isGuest ? '' : 'min:1'],
            'photos.*'       => ['file', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $photoPaths = [];
        foreach ($request->file('photos', []) as $photo) {
            $photoPaths[] = $photo->store('incident_photos', 'public');
        }

        // Guests never type a location — only a GPS pin. Resolve that pin
        // to something a human can actually read (street/barangay via
        // Nominatim, falling back to the nearest known barangay centroid,
        // and only raw coordinates as a last resort) instead of leaving
        // `location` blank, which is what forced every admin view to show
        // naked lat/lng numbers for guest reports.
        $location = $request->location;
        if ($isGuest && ! $location) {
            $location = $locationService->approximateReportLabel($request->latitude, $request->longitude);
        }

        // A pin outside Rosales needs admin eyes before it reaches
        // responders too — even from a trusted logged-in citizen. MDRRMO
        // Rosales' responders shouldn't get auto-dispatched to a wrong
        // pin, a test submission from another town, or anything outside
        // their actual jurisdiction. Only checked when coordinates were
        // actually sent (see isWithinRosales() — a citizen who typed an
        // address without dropping a pin isn't gated by this).
        $isOutsideRosales = ! $locationService->isWithinRosales($request->latitude, $request->longitude);

        $incident = Incident::create([
            'citizen_id'     => $citizen?->id,
            'emergency_type' => $request->emergency_type,
            'location'       => $location,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'description'    => $request->description ?: 'Guest report — location only. Awaiting MDRRMO review.',
            'photo_path'     => $photoPaths ? json_encode($photoPaths) : null,
            'status'         => 'pending',
            // Guest submissions are unverified, and anything outside
            // Rosales needs a human to confirm it's real before
            // responders are pulled in — both sit here until an admin
            // reviews them (see Admin IncidentController::approve).
            // Everything else (logged-in citizen, inside Rosales) is
            // trusted immediately, same as before this change.
            'needs_review'   => $isGuest || $isOutsideRosales,
        ]);

        if ($photoPaths) {
            $analysis = $imageAnalysis->classify($photoPaths[0]);
            if ($analysis) {
                $incident->update([
                    'ai_detected_type' => $analysis['type'],
                    'ai_confidence'    => $analysis['confidence'],
                    'ai_analysis'      => $analysis['notes'],
                    'priority'         => $analysis['priority'],
                ]);
            }
        }

        // Reports needing review never notify responders until an admin
        // approves — see Admin\IncidentController::approve(), which
        // fires this same dispatch call once needs_review flips to
        // false.
        if (! $incident->needs_review) {
            $push->dispatchToRespondersForIncident($incident);
        }

        $message = match(true) {
            $isGuest => 'Report submitted. MDRRMO will review it shortly before it reaches responders.',
            $isOutsideRosales => 'Report submitted. Since this location is outside Rosales, MDRRMO will review it before responders are notified.',
            default => 'Report submitted.',
        };

        return response()->json([
            'message'  => $message,
            'incident' => $incident,
        ], 201);
    }

    public function sos(
        Request $request,
        ImageAnalysisService $imageAnalysis,
        PushNotificationService $push,
        BarangayLocationService $locationService
    ) {
        $citizen = $this->resolveOptionalCitizen($request);
        $isGuest = ! $citizen;

        $validator = Validator::make($request->all(), [
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo'     => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('incident_photos', 'public')
            : null;

        // SOS always carries coordinates (required above for guest and
        // citizen alike), so this check always runs — unlike store()
        // above, where a citizen without a GPS pin skips it entirely.
        $isOutsideRosales = ! $locationService->isWithinRosales($request->latitude, $request->longitude);

        $incident = Incident::create([
            'citizen_id'     => $citizen?->id,
            'emergency_type' => 'SOS Emergency',
            // SOS never carries typed location text — only ever a GPS
            // pin, from a guest or logged-in citizen alike — so this
            // always needs resolving, unlike store() above where only
            // guests hit this path.
            'location'       => $locationService->approximateSosLabel($request->latitude, $request->longitude),
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'description'    => $isGuest ? 'Guest SOS — location only. Awaiting MDRRMO review.' : 'SOS panic-button alert.',
            'photo_path'     => $photoPath ? json_encode([$photoPath]) : null,
            'status'         => 'pending',
            'priority'       => 'critical',
            // Same reasoning as store() above — a pin outside Rosales
            // needs an admin to confirm it before responders get
            // dispatched, even from a trusted logged-in citizen.
            'needs_review'   => $isGuest || $isOutsideRosales,
        ]);

        if ($photoPath) {
            $analysis = $imageAnalysis->classify($photoPath);
            if ($analysis) {
                $incident->update([
                    'ai_detected_type' => $analysis['type'],
                    'ai_confidence'    => $analysis['confidence'],
                    'ai_analysis'      => $analysis['notes'],
                ]);
            }
        }

        if (! $incident->needs_review) {
            $push->dispatchToRespondersForIncident($incident);
        }

        $message = match(true) {
            $isGuest => 'SOS sent. MDRRMO will review it shortly before it reaches responders.',
            $isOutsideRosales => 'SOS sent. Since this location is outside Rosales, MDRRMO will review it before responders are notified.',
            default => 'SOS sent.',
        };

        return response()->json([
            'message'  => $message,
            'incident' => $incident,
        ], 201);
    }

    public function mine(Request $request)
    {
        $incidents = Incident::with('responders')
            ->where('citizen_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($incidents);
    }

    public function assignedToResponder(Request $request)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        $incidents = Incident::with(['citizen', 'responders'])
            ->where('status', '!=', 'resolved')
            // Guest reports pending admin approval must never reach the
            // responder feed — dispatchToRespondersForIncident() is also
            // withheld for them, so this is a belt-and-suspenders check.
            ->where('needs_review', false)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($incidents);
    }

    /**
     * Backup-support model: accepting no longer exclusively claims the
     * incident for one responder. Any number of responders (from the
     * same or different agencies) can join the same incident — this
     * just adds the caller to the pivot table if they aren't on it
     * already. Tapping Accept a second time (e.g. a stale UI, or the
     * app re-sending after a timeout) is a safe no-op thanks to the
     * UNIQUE(incident_id, responder_id) constraint on incident_responder.
     */
    public function accept(Request $request, Incident $incident)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        if ($incident->status === 'resolved') {
            return response()->json(['message' => 'This incident has already been resolved.'], 409);
        }

        $alreadyJoined = $incident->responders()->where('responder_id', $responder->id)->exists();

        if (! $alreadyJoined) {
            $incident->responders()->attach($responder->id, ['accepted_at' => now()]);
        }

        // First acceptance (by anyone) moves the incident out of
        // 'pending' — later backup joins don't need to change status
        // again, it's already 'responding'.
        if ($incident->status === 'pending') {
            $incident->update(['status' => 'responding']);
        }

        $incident->refresh()->load(['responders', 'citizen']);

        return response()->json([
            'message'  => $alreadyJoined
                ? "You're already responding to this incident."
                : 'Mission accepted.',
            'incident' => $incident,
        ]);
    }

    public function decline(Request $request, Incident $incident)
    {
        return response()->json(['message' => 'Mission declined.']);
    }

    /**
     * Marks an incident resolved from the field — see
     * IncidentResolutionScreen (incident_resolution.dart). Only a
     * responder who's actually on this incident (i.e. called accept()
     * already) can resolve it: unlike accept(), this genuinely changes
     * state and closes the mission out, so it isn't safe to leave open
     * to any responder who merely sees it in their feed.
     */
    public function resolve(Request $request, Incident $incident)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        if ($incident->status === 'resolved') {
            return response()->json(['message' => 'This incident has already been resolved.'], 409);
        }

        $isAssigned = $incident->responders()->where('responder_id', $responder->id)->exists();

        if (! $isAssigned) {
            return response()->json(['message' => 'You are not assigned to this incident.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('incident_resolution_photos', 'public')
            : null;

        // Wrapped specifically because this project has no CLI/artisan
        // access on its deployment — migrations get applied by hand via
        // phpMyAdmin (see 2026_09_13_175814_add_resolution_fields_to_
        // incidents_table.php), so a column genuinely missing on the
        // live DB is a real, recurring failure mode here, not a
        // hypothetical one. Without this, that shows up to the
        // responder as an opaque "Could not mark this resolved." with
        // nothing in the app to explain why; this at least logs the
        // real SQL error server-side and tells the responder it's a
        // server problem rather than something they can retry their way
        // out of.
        try {
            $incident->update([
                'status'                => 'resolved',
                'resolution_notes'      => $request->notes,
                'resolution_photo_path' => $photoPath,
            ]);
        } catch (QueryException $e) {
            Log::error('Failed to save incident resolution — possible missing column on incidents table.', [
                'incident_id' => $incident->id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not save the resolution — a server-side database issue. Please notify MDRRMO admin.',
            ], 500);
        }

        $incident->refresh()->load(['responders', 'citizen']);

        return response()->json([
            'message'  => 'Incident marked as resolved.',
            'incident' => $incident,
        ]);
    }
}