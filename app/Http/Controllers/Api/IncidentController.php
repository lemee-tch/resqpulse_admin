<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Responder;
use App\Services\ImageAnalysisService;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IncidentController extends Controller
{
    public function store(Request $request, ImageAnalysisService $imageAnalysis, PushNotificationService $push)
    {
        $validator = Validator::make($request->all(), [
            'emergency_type' => ['required', 'string', 'max:255'],
            'location'       => ['required', 'string', 'max:255'],
            'description'    => ['required', 'string'],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
            'photos'         => ['required', 'array', 'min:1'],
            'photos.*'       => ['file', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $photoPaths = [];
        foreach ($request->file('photos', []) as $photo) {
            $photoPaths[] = $photo->store('incident_photos', 'public');
        }

        $incident = Incident::create([
            'citizen_id'     => $request->user()?->id,
            'emergency_type' => $request->emergency_type,
            'location'       => $request->location,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'description'    => $request->description,
            'photo_path'     => $photoPaths ? json_encode($photoPaths) : null,
            'status'         => 'pending',
        ]);

        if ($photoPaths) {
            $analysis = $imageAnalysis->classify($photoPaths[0]);
            if ($analysis) {
                $incident->update([
                    'ai_detected_type' => $analysis['type'],
                    'ai_confidence'    => $analysis['confidence'],
                    'ai_analysis'      => $analysis['notes'],
                    // AI-suggested priority — best-effort only. If classify()
                    // returned null (no API key, timeout, etc.) this whole
                    // block is skipped and priority stays unset, same as
                    // before; an admin sets it manually from the Incidents
                    // table exactly like today.
                    'priority'         => $analysis['priority'],
                ]);
            }
        }

        $push->dispatchToRespondersForIncident($incident);

        return response()->json(['message' => 'Report submitted.', 'incident' => $incident], 201);
    }

    public function sos(Request $request, ImageAnalysisService $imageAnalysis, PushNotificationService $push)
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
            ? $request->file('photo')->store('incident_photos', 'public')
            : null;

        $incident = Incident::create([
            'citizen_id'     => $request->user()?->id,
            'emergency_type' => 'SOS Emergency',
            'location'       => '',
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'description'    => 'SOS panic-button alert.',
            'photo_path'     => $photoPath ? json_encode([$photoPath]) : null,
            'status'         => 'pending',
            'priority'       => 'critical',
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

        $push->dispatchToRespondersForIncident($incident);

        return response()->json(['message' => 'SOS sent.', 'incident' => $incident], 201);
    }

    public function mine(Request $request)
    {
        $incidents = Incident::where('citizen_id', $request->user()->id)
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

        $incidents = Incident::with('citizen')
            ->where('status', '!=', 'resolved')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (Incident $incident) use ($responder) {
                $routingType = $incident->ai_detected_type ?? $incident->emergency_type;
                return in_array($responder->agency, PushNotificationService::agenciesFor($routingType), true);
            })
            ->values();

        return response()->json($incidents);
    }
}