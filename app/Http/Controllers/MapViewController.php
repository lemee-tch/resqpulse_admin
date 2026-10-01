<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\EvacuationCenter;
use App\Services\RosalesBoundaryService;

class MapViewController extends Controller
{
    public function index(RosalesBoundaryService $boundaryService)
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

        // Fetch/cache now lives in RosalesBoundaryService — shared with
        // AuthController::dashboard(), which draws the same boundary
        // highlight on the Dashboard's small preview map. Either page
        // visited first populates the one 30-day cache entry for both.
        $boundaryData = $boundaryService->getBoundaryData();

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
    public function refreshBoundaries(RosalesBoundaryService $boundaryService)
    {
        $boundaryService->refresh();

        return redirect()->route('mapview')
            ->with('success', 'Boundary and barangay data refreshed from OpenStreetMap.');
    }
}