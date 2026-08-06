<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Services\ImageAnalysisService;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class IncidentController extends Controller
{
    public function store(Request $request, PushNotificationService $push)
    {
        $validator = Validator::make($request->all(), [
            'emergency_type' => ['required', 'string'],
            'location'       => ['required', 'string'],
            'description'    => ['required', 'string'],
            // Expect an array of images, each max 5MB
            'photos'         => ['required', 'array', 'min:3', 'max:5'],
            'photos.*'       => ['file', 'image', 'max:5120'],
            // Sent by the app whenever it resolved GPS or a barangay lookup
            // (see report_incident.dart) — optional because a person can
            // submit a report before either finishes resolving.
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Process and store multiple photos
        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                $photoPaths[] = $file->store('incident_photos', 'public');
            }
        }

        $incident = Incident::create([
            'citizen_id'     => $request->user()->id,
            'emergency_type' => $request->emergency_type,
            'location'       => $request->location,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'description'    => $request->description,
            // Save paths as JSON array string (Ensure your Incident model casts 'photo_path' to array/json)
            'photo_path'     => json_encode($photoPaths),
            'status'         => 'pending',
        ]);

        // Notify the responder agency (or agencies) that handle this
        // emergency type — see PushNotificationService::AGENCY_MAP for
        // the routing rules. Best-effort: failures here never block the
        // report from having been saved successfully above.
        $push->dispatchToRespondersForIncident($incident);

        return response()->json(['message' => 'Report submitted', 'incident' => $incident], 201);
    }

    public function mine(Request $request)
    {
        $incidents = Incident::where('citizen_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($incidents);
    }

    /**
     * Incidents relevant to the logged-in responder's agency — uses the
     * exact same routing rules as push dispatch (PushNotificationService
     * ::agenciesFor()) so what a responder sees in-app always matches what
     * they were pushed for. Excludes resolved incidents since those no
     * longer need active response.
     *
     * Eager-loads `citizen` so the responder app's incident detail screen
     * ("Reported By" / "Contact") has real data instead of falling back
     * to "Not available" for every incident.
     */
    public function assignedToResponder(Request $request)
    {
        $responder = $request->user();

        if (! $responder instanceof \App\Models\Responder) {
            return response()->json(['message' => 'This endpoint is for responder accounts only.'], 403);
        }

        $incidents = Incident::with('citizen')
            ->where('status', '!=', 'resolved')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function ($incident) use ($responder) {
                $routingType = $incident->ai_detected_type ?? $incident->emergency_type;
                return in_array($responder->agency, PushNotificationService::agenciesFor($routingType), true);
            })
            ->values();

        return response()->json($incidents);
    }

    /**
     * SOS panic button — one photo (optional), GPS coordinates required.
     * If a photo was captured, it's run through AI classification so
     * admins get a suggested emergency type before they even open it.
     * Classification failure never blocks the SOS from saving.
     */
    public function sos(Request $request, PushNotificationService $push)
    {
        $validator = Validator::make($request->all(), [
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo'     => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('sos_photos', 'public')
            : null;

        $lat = $request->latitude;
        $lng = $request->longitude;

        $aiResult = $photoPath
            ? app(ImageAnalysisService::class)->classify($photoPath)
            : null;

        $incident = Incident::create([
            'citizen_id'       => $request->user()->id,
            'emergency_type'   => 'SOS Emergency',
            'ai_detected_type' => $aiResult['type'] ?? null,
            'ai_confidence'    => $aiResult['confidence'] ?? null,
            'ai_analysis'      => $aiResult['notes'] ?? null,
            'location'         => $this->resolveSosLocationLabel($lat, $lng),
            'latitude'         => $lat,
            'longitude'        => $lng,
            'description'      => 'Emergency SOS triggered from the mobile app. Immediate response required.',
            'photo_path'       => $photoPath,
            'status'           => 'pending',
            'priority'         => 'critical',
        ]);

        // Routes by AI-detected type when a photo was classified (e.g. a
        // photo that looks like a fire also notifies BFP); otherwise falls
        // back to MDRRMO as the general dispatcher. See
        // PushNotificationService::AGENCY_MAP for the full routing table.
        $push->dispatchToRespondersForIncident($incident);

        return response()->json(['message' => 'SOS sent', 'incident' => $incident], 201);
    }

    /**
     * Turns raw SOS coordinates into a human-readable label for the
     * admin panel (e.g. "SOS Alert near San Vicente, Rosales — Lat
     * 15.89..., Lng 120.62...") instead of just bare numbers, so an
     * MDRRMO staffer glancing at the SOS list doesn't have to open Google
     * Maps just to know roughly where it is.
     *
     * Best-effort only: a short timeout (3s) and a hard try/catch mean a
     * slow/unreachable geocoding service NEVER blocks or fails an SOS —
     * it just falls back to the raw coordinates, same as before this
     * feature existed. The exact lat/lng are always saved regardless, so
     * responders never lose precision even if this lookup fails.
     */
    private function resolveSosLocationLabel(float $lat, float $lng): string
    {
        $fallback = "SOS Alert — Lat {$lat}, Lng {$lng}";

        try {
            $response = Http::withHeaders(['User-Agent' => 'ResQPulse-MDRRMO-Rosales/1.0'])
                ->timeout(3)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format'      => 'json',
                    'lat'         => $lat,
                    'lon'         => $lng,
                    'zoom'        => 18,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return $fallback;
            }

            $address = $response->json('address');
            if (! is_array($address)) {
                return $fallback;
            }

            $place = $address['suburb']
                ?? $address['village']
                ?? $address['neighbourhood']
                ?? $address['road']
                ?? null;

            if (! $place) {
                return $fallback;
            }

            return "SOS Alert (approx. {$place} area), — Lat {$lat}, Lng {$lng}";
        } catch (\Throwable $e) {
            Log::warning('SOS reverse geocode failed, falling back to raw coordinates', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }
}