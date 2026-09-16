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
            // Responders have no live GPS column — the only location we can
            // truthfully show for one is the incident they accepted. This
            // eager-loads just what the map popup needs (name + agency);
            // `pivot.accepted_at` comes along automatically from the
            // withPivot() on Incident::responders() and survives this
            // column restriction since it's added at the relation-builder
            // level, not part of the responders table select below.
            ->with(['responders' => function ($q) {
                $q->select('responders.id', 'responders.full_name', 'responders.first_name', 'responders.last_name', 'responders.agency', 'responders.unit_station');
            }])
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
        // A generous bounding box around Rosales, Pangasinan. Without this,
        // the name-only filter below can match a completely unrelated
        // "Rosales" admin boundary anywhere in the world (there are several
        // — e.g. in Mexico and Spain) and silently return a tiny, wrong
        // polygon instead of failing loudly.
        $bbox = '15.78,120.52,16.02,120.75';

        $query = '[out:json][timeout:90];'
            . 'area["name"="Rosales"]["boundary"="administrative"]["admin_level"="8"](' . $bbox . ')->.a;'
            . '('
            . 'relation["boundary"="administrative"]["admin_level"="8"]["name"="Rosales"](' . $bbox . ');'
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
                $response = Http::timeout(90)->asForm()->post($url, ['data' => $query]);

                if ($response->successful()) {
                    $data = $response->json() ?? ['elements' => []];

                    // A real municipal boundary has hundreds of vertices at
                    // minimum. If every level-8 relation we got back is this
                    // sparse, the name-only match almost certainly grabbed
                    // the wrong "Rosales" (or an incomplete one) — treat it
                    // as a failed fetch and let the next endpoint (or the
                    // empty-elements fallback) take over rather than caching
                    // 30 days of a broken boundary.
                    if ($this->hasUsableMunicipalBoundary($data)) {
                        return $data;
                    }

                    Log::warning('Overpass boundary fetch returned a suspiciously sparse Rosales geometry', ['url' => $url]);
                    continue;
                }

                Log::warning('Overpass boundary fetch failed', ['url' => $url, 'status' => $response->status()]);
            } catch (\Throwable $e) {
                Log::warning('Overpass boundary fetch threw an exception', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return ['elements' => []];
    }

    /**
     * Sanity check on the level-8 (municipal) relation before we trust and
     * cache it. Rosales is a real, moderately large municipality — its
     * boundary should have a non-trivial number of way members and total
     * vertices. A handful of members/points means the query almost
     * certainly matched the wrong "Rosales" or got truncated data.
     */
    private function hasUsableMunicipalBoundary(array $data): bool
    {
        foreach (($data['elements'] ?? []) as $el) {
            if (($el['type'] ?? null) !== 'relation' || ($el['tags']['admin_level'] ?? null) !== '8') {
                continue;
            }

            $members = $el['members'] ?? [];
            $pointCount = 0;
            foreach ($members as $member) {
                $pointCount += count($member['geometry'] ?? []);
            }

            if (count($members) >= 4 && $pointCount >= 100) {
                return true;
            }
        }

        return false;
    }
}