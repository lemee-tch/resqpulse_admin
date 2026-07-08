<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IncidentController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emergency_type' => ['required', 'string'],
            'location'       => ['required', 'string'],
            'description'    => ['required', 'string'],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
            // Expect an array of images, each max 5MB
            'photos'         => ['required', 'array', 'min:3', 'max:5'],
            'photos.*'       => ['file', 'image', 'max:5120'],
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

        return response()->json(['message' => 'Report submitted', 'incident' => $incident], 201);
    }

    public function mine(Request $request)
    {
        $incidents = Incident::where('citizen_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($incidents);
    }
}