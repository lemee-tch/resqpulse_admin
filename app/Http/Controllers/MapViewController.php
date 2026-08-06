<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\EvacuationCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapViewController extends Controller
{
    public function index()
    {
        // Resolved incidents disappear from the map 24h after their last
        // update (i.e. 24h after being marked resolved). Everything else
        // (pending/responding) always shows.
        $incidents = Incident::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($query) {
                $query->where('status', '!=', 'resolved')
                      ->orWhere('updated_at', '>=', now()->subDay());
            })
            ->select('id', 'emergency_type', 'location', 'latitude', 'longitude', 'description', 'status', 'created_at', 'updated_at')
            ->orderByDesc('created_at')
            ->get();

        $evacCenters = EvacuationCenter::select('id', 'name', 'barangay', 'latitude', 'longitude', 'capacity', 'occupancy', 'status')
            ->get();

        $boundaryData = Cache::remember('rosales_boundary_and_barangays', now()->addDays(30), function () {
            return $this->fetchBoundaryData();
        });

        return view('mapview', [
            'incidents' => $incidents,
            'evacCenters' => $evacCenters,
            'boundaryData' => $boundaryData,
        ]);
    }

    /**
     * Manual refresh — clears the cache so the next page load re-fetches.
     * There's no CLI/cron access, so this is the only way to force an
     * update (e.g. if OpenStreetMap adds a missing barangay boundary).
     */
    public function refreshBoundaries()
    {
        Cache::forget('rosales_boundary_and_barangays');

        return redirect()->route('mapview')
            ->with('success', 'Boundary and barangay data refreshed from OpenStreetMap.');
    }

    /**
     * Fetches both the Rosales municipal boundary (admin_level 8, one
     * relation) and every barangay inside it (admin_level 10 — used only
     * for name + centroid label; no per-barangay borders are drawn) in a
     * single Overpass query. Tries a couple of public mirrors since the
     * main overpass-api.de endpoint is sometimes rate-limited.
     */
    private function fetchBoundaryData(): array
    {
        $query = '[out:json][timeout:60];'
            . 'area["name"="Rosales"]["boundary"="administrative"]["admin_level"="8"]->.a;'
            . '('
            . 'relation["boundary"="administrative"]["admin_level"="8"]["name"="Rosales"];'
            . 'relation["admin_level"="10"](area.a);'
            . ');'
            . 'out geom;';

        $endpoints = [
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
            'https://lz4.overpass-api.de/api/interpreter',
        ];

        foreach ($endpoints as $url) {
            try {
                $response = Http::timeout(60)->asForm()->post($url, ['data' => $query]);

                if ($response->successful()) {
                    return $response->json() ?? ['elements' => []];
                }

                Log::warning('Overpass boundary fetch failed', ['url' => $url, 'status' => $response->status()]);
            } catch (\Throwable $e) {
                Log::warning('Overpass boundary fetch threw an exception', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return ['elements' => []];
    }
}