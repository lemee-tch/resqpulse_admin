<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches (and caches) the Rosales municipal boundary from
 * OpenStreetMap's Overpass API.
 *
 * This used to live entirely inside MapViewController, private and
 * only reachable from the Map View page. The Dashboard's small preview
 * map ("Incident Map" card) needed the same municipal boundary so it
 * can draw the same "dim everything outside Rosales + yellow outline"
 * highlight the full Map View already has — so the fetch/cache logic
 * moved here, where both controllers (and anything else that ever
 * needs it) can share one 30-day cache entry instead of each keeping
 * its own copy of this Overpass query.
 *
 * Barangay-level data is deliberately NOT fetched here, even though an
 * earlier version of this service tried to (via an admin_level=10
 * sub-query nested inside the municipal area). Confirmed directly
 * against Rosales' real OSM relation that it has ZERO barangay-level
 * administrative boundaries mapped at all — that sub-query could never
 * have returned anything. Every barangay label on the map now comes
 * from BarangayLocationService::allCoordinates() instead: a
 * hand-verified centroid table this app already maintains for
 * geocoding fallback, rather than waiting on OpenStreetMap to map them.
 */
class RosalesBoundaryService
{
    public const CACHE_KEY = 'rosales_boundary_and_barangays';

    public function getBoundaryData(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDays(30), function () {
            return $this->fetchBoundaryData();
        });
    }

    /**
     * Manual refresh — clears the cache so the next page load (Map
     * View or Dashboard, whichever is visited first) re-fetches. There's
     * no CLI/cron access on this deployment, so this is the only way to
     * force an update (e.g. if OpenStreetMap's copy of the municipal
     * boundary itself is edited/corrected). Barangay labels are no
     * longer part of what gets refreshed here — they come from
     * BarangayLocationService's own static table, not Overpass.
     */
    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Fetches the Rosales municipal boundary — a single relation,
     * admin_level=6. Philippine municipalities are tagged at level 6 in
     * OSM, not the level 8 this query originally assumed; level 8 is
     * one tier too specific here and matched nothing, which is why this
     * fetch had been silently returning empty for the full 30-day cache
     * window. Tries a couple of public mirrors since the main
     * overpass-api.de endpoint is sometimes overloaded/rate-limited —
     * each one gets a tight 20s budget rather than waiting it out, so a
     * bad mirror doesn't eat the whole request before the next one gets
     * a turn.
     */
    private function fetchBoundaryData(): array
    {
        // A generous bounding box around Rosales, Pangasinan. Without this,
        // the name-only filter below can match a completely unrelated
        // "Rosales" admin boundary anywhere in the world (there are several
        // — e.g. in Mexico and Spain) and silently return a tiny, wrong
        // polygon instead of failing loudly.
        $bbox = '15.78,120.52,16.02,120.75';

        // admin_level=6 — confirmed against Rosales' real OSM relation
        // (id 16054118). Not 8: that was this query's original (wrong)
        // assumption, and it matched nothing.
        //
        // [timeout:20] here matches the Http::timeout(20) below rather
        // than the 90s both used to say — a real response (success OR a
        // clean "server busy" error) has consistently come back in well
        // under 10s in testing. 90s per endpoint meant a worst case of
        // 270s across all three mirrors, which risked running into
        // shared hosting's PHP max_execution_time and killing the whole
        // request before it ever reached a healthy mirror — turning a
        // recoverable "try the next one" into a hard failure instead.
        $query = '[out:json][timeout:20];'
            . 'relation["boundary"="administrative"]["admin_level"="6"]["name"="Rosales"](' . $bbox . ');'
            . 'out geom;';

        $endpoints = [
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
            'https://lz4.overpass-api.de/api/interpreter',
        ];

        foreach ($endpoints as $url) {
            try {
                // Overpass's Apache front-end returns 406 Not Acceptable
                // for any request with no User-Agent header at all —
                // confirmed directly, and it was rejecting every one of
                // the three mirrors above for that reason alone, fully
                // independent of the admin_level bug above. Guzzle (and
                // so Laravel's Http client) doesn't send one by default.
                $response = Http::timeout(20)->asForm()
                    ->withHeaders(['User-Agent' => 'ResQPulse-MDRRMO-Rosales/1.0'])
                    ->post($url, ['data' => $query]);

                if ($response->successful()) {
                    $data = $response->json() ?? ['elements' => []];

                    // A real municipal boundary has hundreds of vertices at
                    // minimum. If every level-6 relation we got back is this
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
     * Sanity check on the level-6 (municipal) relation before we trust and
     * cache it. Rosales is a real, moderately large municipality — its
     * boundary should have a non-trivial number of way members and total
     * vertices. A handful of members/points means the query almost
     * certainly matched the wrong "Rosales" or got truncated data.
     */
    private function hasUsableMunicipalBoundary(array $data): bool
    {
        foreach (($data['elements'] ?? []) as $el) {
            if (($el['type'] ?? null) !== 'relation' || ($el['tags']['admin_level'] ?? null) !== '6') {
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