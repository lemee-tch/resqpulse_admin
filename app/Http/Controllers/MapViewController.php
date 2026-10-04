<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\EvacuationCenter;
use App\Services\BarangayLocationService;
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

        // Instant — reads the cache or the bundled fallback, never
        // touches the network. Fetch/cache logic lives in
        // RosalesBoundaryService, shared with AuthController::dashboard(),
        // which draws the same boundary highlight on the Dashboard's small
        // preview map. See RosalesBoundaryService's docblock for why a
        // live fetch never happens here.
        $boundaryData = $boundaryService->getBoundaryData();

        // Barangay name labels — NOT from OpenStreetMap (it has no
        // barangay-level boundaries mapped for Rosales at all), but from
        // this app's own verified centroid table, already relied on
        // elsewhere for geocoding fallback. See BarangayLocationService.
        $barangays = BarangayLocationService::allCoordinates();

        return view('mapview', [
            'incidents' => $incidents,
            'evacCenters' => $evacCenters,
            'boundaryData' => $boundaryData,
            'barangays' => $barangays,
        ]);
    }

    /**
     * "Refresh Map" — the one place that attempts a live Overpass fetch
     * (see RosalesBoundaryService::refreshAndGet()). Everything else on
     * this page reads instantly from cache/fallback; this button alone
     * takes the few-seconds hit of actually trying Overpass, so that
     * risk is confined to a deliberate, infrequent user action instead
     * of gambled on every visitor's ordinary page load.
     */
    public function refreshBoundaries(RosalesBoundaryService $boundaryService)
    {
        $boundaryService->refreshAndGet();

        return redirect()->route('mapview')
            ->with('success', 'Boundary data refreshed from OpenStreetMap.');
    }
}