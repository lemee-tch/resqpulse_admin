<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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
 *
 * Live fetches go through fetchBoundaryData() below, but this app's
 * production host appears to be IP-blocked by Overpass's main servers
 * (overpass-api.de and its lz4 mirror) specifically — confirmed, at
 * length: every single attempt from this host failed (406, then
 * timeouts) across every code version tried, including ones that were
 * verified byte-for-byte correct, while the identical query succeeded
 * immediately and repeatedly from an unrelated network. The
 * independently-operated overpass.kumi.systems mirror doesn't fail the
 * same way, but isn't reliable enough on its own to depend on. So a
 * live fetch is no longer treated as required: getBoundaryData() falls
 * back to FALLBACK_BOUNDARY — a verified-correct copy of Rosales' real
 * boundary (relation 16054118), fetched once from an unblocked network
 * and bundled directly into this file — whenever a live fetch doesn't
 * come back usable. The map now always renders the Rosales highlight
 * correctly regardless of whether Overpass can be reached from this
 * host; "Refresh Map" still retries the live fetch, for whenever that
 * changes.
 */
class RosalesBoundaryService
{
    public const CACHE_KEY = 'rosales_boundary_and_barangays';

    /**
     * Short-lived marker set whenever a live fetch attempt fails to
     * produce a usable boundary. Without this, a bad patch (e.g. the
     * IP-block described above) would mean every single page load
     * re-runs the full ~12s three-mirror pool fetch, fails again, and
     * only then falls back — slowing the page down for no benefit.
     * While this is set, getBoundaryData() skips straight to
     * FALLBACK_BOUNDARY without attempting Overpass at all. "Refresh
     * Map" clears it (see refresh() below), so a manual retry always
     * gets a fresh live attempt regardless of this backoff window.
     */
    private const RECENT_FAILURE_KEY = 'rosales_boundary_recent_failure';

    /**
     * Returns the boundary data to draw, preferring a real cached
     * success, then a fresh live attempt, then — if neither is
     * available — the bundled fallback. This never returns an empty/
     * unusable result the way the old cache-the-fetch-result-no-matter-
     * what approach could: either real data or FALLBACK_BOUNDARY, always.
     */
    public function getBoundaryData(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        // A live fetch failed recently — don't hammer Overpass again on
        // every page load during a bad patch. Serve the bundled
        // fallback immediately instead of re-waiting ~12s just to fail
        // the same way again.
        if (Cache::has(self::RECENT_FAILURE_KEY)) {
            return self::FALLBACK_BOUNDARY;
        }

        $data = $this->fetchBoundaryData();

        if ($this->hasUsableMunicipalBoundary($data)) {
            Cache::put(self::CACHE_KEY, $data, now()->addDays(30));

            return $data;
        }

        // Deliberately NOT cached for 30 days — that was the original
        // bug (a bad fetch used to get cached as if it were a good one,
        // silently going "boundary unavailable" for a month). Short
        // enough to avoid retrying on every request during an outage,
        // short enough to pick back up quickly once Overpass is
        // reachable again.
        Cache::put(self::RECENT_FAILURE_KEY, true, now()->addHours(6));

        return self::FALLBACK_BOUNDARY;
    }

    /**
     * Manual refresh — clears both the success cache and the recent-
     * failure backoff marker, so the next page load always attempts a
     * fresh live fetch regardless of how recently a previous attempt
     * failed. There's no CLI/cron access on this deployment, so this is
     * the only way to force an update (e.g. if OpenStreetMap's copy of
     * the municipal boundary itself is edited/corrected, or to check
     * whether Overpass has become reachable from this host again).
     * Barangay labels are no longer part of what gets refreshed here —
     * they come from BarangayLocationService's own static table, not
     * Overpass.
     */
    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::RECENT_FAILURE_KEY);
    }

    /**
     * Fetches the Rosales municipal boundary — a single relation,
     * admin_level=6. Philippine municipalities are tagged at level 6 in
     * OSM, not the level 8 this query originally assumed; level 8 is
     * one tier too specific here and matched nothing, which is why this
     * fetch had been silently returning empty for the full 30-day cache
     * window.
     *
     * Tries three public mirrors — CONCURRENTLY, not one after another.
     * A sequential try-each-in-turn loop was tried first, with the
     * per-endpoint timeout shrunk twice (90s, then 20s, then a 5s
     * connect / 10s total cap) — and even the tightest version (30s
     * worst case for all three in a row) still tripped this host's own
     * nginx gateway timeout, killing the whole page with a hard 504
     * before ever reaching a working mirror. That's worse than the
     * graceful "boundary unavailable" fallback this is supposed to
     * degrade to — a 504 kills the request before it can even reach
     * that fallback. Firing all three at once via Http::pool() bounds
     * the whole operation by the SLOWEST single mirror (~12s worst
     * case) instead of the sum of all three (~36s), while keeping the
     * same three-mirror resilience.
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
        $query = '[out:json][timeout:12];'
            . 'relation["boundary"="administrative"]["admin_level"="6"]["name"="Rosales"](' . $bbox . ');'
            . 'out geom;';

        $endpoints = [
            'overpass-api.de' => 'https://overpass-api.de/api/interpreter',
            'kumi'            => 'https://overpass.kumi.systems/api/interpreter',
            'lz4'             => 'https://lz4.overpass-api.de/api/interpreter',
        ];

        // Overpass's Apache front-end returns 406 Not Acceptable for any
        // request with no User-Agent header at all — confirmed directly
        // against all three mirrors. Guzzle (and so Laravel's Http
        // client) doesn't send one by default.
        $headers = ['User-Agent' => 'ResQPulse-MDRRMO-Rosales/1.0'];

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($name, $url) => $pool->as($name)
                    ->connectTimeout(5)
                    ->timeout(12)
                    ->asForm()
                    ->withHeaders($headers)
                    ->post($url, ['data' => $query]),
                array_keys($endpoints),
                $endpoints
            ));
        } catch (\Throwable $e) {
            // A failure INSIDE the pool (timeout, connection refused,
            // etc.) resolves to an exception object in $responses for
            // that key, not a throw here — this catch is only for
            // something going wrong with the pool mechanism itself.
            Log::warning('Overpass boundary pool fetch threw an exception', ['error' => $e->getMessage()]);
            return ['elements' => []];
        }

        foreach ($endpoints as $name => $url) {
            $response = $responses[$name] ?? null;

            if (! $response instanceof Response) {
                $error = $response instanceof \Throwable ? $response->getMessage() : 'no response';
                Log::warning('Overpass boundary fetch failed', ['url' => $url, 'error' => $error]);
                continue;
            }

            if (! $response->successful()) {
                Log::warning('Overpass boundary fetch failed', ['url' => $url, 'status' => $response->status()]);
                continue;
            }

            $data = $response->json() ?? ['elements' => []];

            // A real municipal boundary has hundreds of vertices at
            // minimum. If every level-6 relation we got back is this
            // sparse, the name-only match almost certainly grabbed the
            // wrong "Rosales" (or an incomplete one) — treat it as a
            // failed fetch rather than caching 30 days of a broken
            // boundary.
            if ($this->hasUsableMunicipalBoundary($data)) {
                return $data;
            }

            Log::warning('Overpass boundary fetch returned a suspiciously sparse Rosales geometry', ['url' => $url]);
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

    /**
     * A verified-correct copy of Rosales' real municipal boundary — OSM
     * relation 16054118, admin_level=6 — fetched directly by relation ID
     * on 2026-10-04 from a network Overpass's main servers don't block
     * (see this class's docblock above for why that distinction matters
     * here). 9 "outer" way members, 133 coordinate points total — well
     * past hasUsableMunicipalBoundary()'s own sanity thresholds (≥4
     * members, ≥100 points), because it's the same real geometry that
     * check was written to recognize as genuine.
     *
     * Trimmed to only the fields renderBoundaryAndLabels() (in
     * mapview.blade.php) and renderDashboardBoundary() (in
     * dashboard.blade.php) actually read — type, members[].{type, ref,
     * role, geometry}, tags.{admin_level, boundary, name, is_in:state}
     * — dropping bounds, name:ko, postal_code, wikidata and wikipedia
     * from the raw Overpass response. Neither blade file ever reads a
     * relation's own "bounds" field; both compute their own
     * google.maps.LatLngBounds from the ring points instead, so
     * dropping it costs nothing.
     *
     * This is real geographic/administrative data, not a hand-
     * transcribed approximation — it was generated programmatically
     * from Overpass's own JSON response (a direct
     * `relation(16054118);out geom;` query) specifically to rule out
     * transcription errors on 133 coordinate pairs. If Rosales'
     * boundary is ever corrected in OpenStreetMap, this will go stale;
     * regenerate it the same way rather than hand-editing coordinates
     * below.
     */
    private const FALLBACK_BOUNDARY = [
        'elements' => [
            [
                'type' => 'relation',
                'id' => 16054118,
                'members' => [
                    [
                        'type' => 'way',
                        'ref' => 1187078846,
                        'role' => 'outer',
                        'geometry' => [
                            [
                                'lat' => 15.9294283,
                                'lon' => 120.6241514,
                            ],
                            [
                                'lat' => 15.9295714,
                                'lon' => 120.6399586,
                            ],
                            [
                                'lat' => 15.9259698,
                                'lon' => 120.6493096,
                            ],
                            [
                                'lat' => 15.9169568,
                                'lon' => 120.647567,
                            ],
                            [
                                'lat' => 15.9154888,
                                'lon' => 120.6562382,
                            ],
                        ],
                    ],
                    [
                        'type' => 'way',
                        'ref' => 1191850096,
                        'role' => 'outer',
                        'geometry' => [
                            [
                                'lat' => 15.9154888,
                                'lon' => 120.6562382,
                            ],
                            [
                                'lat' => 15.9143912,
                                'lon' => 120.6588152,
                            ],
                            [
                                'lat' => 15.9132707,
                                'lon' => 120.66607,
                            ],
                        ],
                    ],
                    [
                        'type' => 'way',
                        'ref' => 1187078851,
                        'role' => 'outer',
                        'geometry' => [
                            [
                                'lat' => 15.9132707,
                                'lon' => 120.66607,
                            ],
                            [
                                'lat' => 15.8949282,
                                'lon' => 120.6630112,
                            ],
                        ],
                    ],
                    [
                        'type' => 'way',
                        'ref' => 1187075158,
                        'role' => 'outer',
                        'geometry' => [
                            [
                                'lat' => 15.8949282,
                                'lon' => 120.6630112,
                            ],
                            [
                                'lat' => 15.8579935,
                                'lon' => 120.6566976,
                            ],
                            [
                                'lat' => 15.8499021,
                                'lon' => 120.6764995,
                            ],
                            [
                                'lat' => 15.8307103,
                                'lon' => 120.6836154,
                            ],
                        ],
                    ],
                    [
                        'type' => 'way',
                        'ref' => 1187075165,
                        'role' => 'outer',
                        'geometry' => [
                            [
                                'lat' => 15.8171016,
                                'lon' => 120.6115741,
                            ],
                            [
                                'lat' => 15.8200028,
                                'lon' => 120.6180615,
                            ],
                            [
                                'lat' => 15.8207332,
                                'lon' => 120.623315,
                            ],
                            [
                                'lat' => 15.8224001,
                                'lon' => 120.6410392,
                            ],
                            [
                                'lat' => 15.824143,
                                'lon' => 120.6455145,
                            ],
                            [
                                'lat' => 15.8251417,
                                'lon' => 120.6500316,
                            ],
                            [
                                'lat' => 15.8245807,
                                'lon' => 120.6547272,
                            ],
                            [
                                'lat' => 15.8248112,
                                'lon' => 120.6560992,
                            ],
                            [
                                'lat' => 15.8253555,
                                'lon' => 120.6572308,
                            ],
                            [
                                'lat' => 15.8262269,
                                'lon' => 120.6570513,
                            ],
                            [
                                'lat' => 15.8264048,
                                'lon' => 120.6574817,
                            ],
                            [
                                'lat' => 15.8277367,
                                'lon' => 120.6613085,
                            ],
                            [
                                'lat' => 15.8273947,
                                'lon' => 120.6659791,
                            ],
                            [
                                'lat' => 15.8265276,
                                'lon' => 120.6666811,
                            ],
                            [
                                'lat' => 15.8261878,
                                'lon' => 120.6683659,
                            ],
                            [
                                'lat' => 15.8270158,
                                'lon' => 120.6693401,
                            ],
                            [
                                'lat' => 15.8282235,
                                'lon' => 120.66925,
                            ],
                            [
                                'lat' => 15.8283627,
                                'lon' => 120.6701818,
                            ],
                            [
                                'lat' => 15.8285919,
                                'lon' => 120.670335,
                            ],
                            [
                                'lat' => 15.8291845,
                                'lon' => 120.6704246,
                            ],
                            [
                                'lat' => 15.8314056,
                                'lon' => 120.6734994,
                            ],
                            [
                                'lat' => 15.8314933,
                                'lon' => 120.6762779,
                            ],
                            [
                                'lat' => 15.8326185,
                                'lon' => 120.6778526,
                            ],
                            [
                                'lat' => 15.8331226,
                                'lon' => 120.6799197,
                            ],
                            [
                                'lat' => 15.8302563,
                                'lon' => 120.680351,
                            ],
                            [
                                'lat' => 15.8307103,
                                'lon' => 120.6836154,
                            ],
                        ],
                    ],
                    [
                        'type' => 'way',
                        'ref' => 828693786,
                        'role' => 'outer',
                        'geometry' => [
                            [
                                'lat' => 15.8789836,
                                'lon' => 120.58822,
                            ],
                            [
                                'lat' => 15.8782605,
                                'lon' => 120.5884216,
                            ],
                            [
                                'lat' => 15.8751499,
                                'lon' => 120.5894061,
                            ],
                            [
                                'lat' => 15.8735326,
                                'lon' => 120.5899179,
                            ],
                            [
                                'lat' => 15.871029,
                                'lon' => 120.5906368,
                            ],
                            [
                                'lat' => 15.8682643,
                                'lon' => 120.5914306,
                            ],
                            [
                                'lat' => 15.8679251,
                                'lon' => 120.5915704,
                            ],
                            [
                                'lat' => 15.8609463,
                                'lon' => 120.5944477,
                            ],
                            [
                                'lat' => 15.8595334,
                                'lon' => 120.5950302,
                            ],
                            [
                                'lat' => 15.8567881,
                                'lon' => 120.5956793,
                            ],
                            [
                                'lat' => 15.8529643,
                                'lon' => 120.5973396,
                            ],
                            [
                                'lat' => 15.8524457,
                                'lon' => 120.5980128,
                            ],
                            [
                                'lat' => 15.8523935,
                                'lon' => 120.5980128,
                            ],
                            [
                                'lat' => 15.8513517,
                                'lon' => 120.5986512,
                            ],
                            [
                                'lat' => 15.8484593,
                                'lon' => 120.6010383,
                            ],
                            [
                                'lat' => 15.8446108,
                                'lon' => 120.60362,
                            ],
                            [
                                'lat' => 15.8437696,
                                'lon' => 120.6041215,
                            ],
                            [
                                'lat' => 15.8394746,
                                'lon' => 120.6067139,
                            ],
                            [
                                'lat' => 15.8389173,
                                'lon' => 120.6069511,
                            ],
                            [
                                'lat' => 15.8388934,
                                'lon' => 120.6069611,
                            ],
                            [
                                'lat' => 15.8387778,
                                'lon' => 120.6070127,
                            ],
                            [
                                'lat' => 15.8383599,
                                'lon' => 120.607194,
                            ],
                            [
                                'lat' => 15.8363601,
                                'lon' => 120.6076661,
                            ],
                            [
                                'lat' => 15.8344635,
                                'lon' => 120.6082159,
                            ],
                            [
                                'lat' => 15.8277697,
                                'lon' => 120.6103027,
                            ],
                            [
                                'lat' => 15.8246344,
                                'lon' => 120.6109115,
                            ],
                            [
                                'lat' => 15.8238428,
                                'lon' => 120.6110685,
                            ],
                            [
                                'lat' => 15.8211067,
                                'lon' => 120.6113005,
                            ],
                            [
                                'lat' => 15.818979,
                                'lon' => 120.61157,
                            ],
                            [
                                'lat' => 15.8171016,
                                'lon' => 120.6115741,
                            ],
                        ],
                    ],
                    [
                        'type' => 'way',
                        'ref' => 1238259158,
                        'role' => 'outer',
                        'geometry' => [
                            [
                                'lat' => 15.8789836,
                                'lon' => 120.58822,
                            ],
                            [
                                'lat' => 15.8801203,
                                'lon' => 120.5946254,
                            ],
                            [
                                'lat' => 15.8818795,
                                'lon' => 120.594136,
                            ],
                            [
                                'lat' => 15.8865247,
                                'lon' => 120.5930453,
                            ],
                        ],
                    ],
                    [
                        'type' => 'way',
                        'ref' => 1187078848,
                        'role' => 'outer',
                        'geometry' => [
                            [
                                'lat' => 15.9092766,
                                'lon' => 120.6234068,
                            ],
                            [
                                'lat' => 15.9092563,
                                'lon' => 120.6233892,
                            ],
                            [
                                'lat' => 15.9084257,
                                'lon' => 120.6226194,
                            ],
                            [
                                'lat' => 15.9076132,
                                'lon' => 120.6220239,
                            ],
                            [
                                'lat' => 15.9065891,
                                'lon' => 120.6213963,
                            ],
                            [
                                'lat' => 15.9061351,
                                'lon' => 120.6209671,
                            ],
                            [
                                'lat' => 15.9055625,
                                'lon' => 120.6204414,
                            ],
                            [
                                'lat' => 15.9049382,
                                'lon' => 120.6197977,
                            ],
                            [
                                'lat' => 15.9042495,
                                'lon' => 120.6190769,
                            ],
                            [
                                'lat' => 15.9029055,
                                'lon' => 120.6187463,
                            ],
                            [
                                'lat' => 15.9016364,
                                'lon' => 120.6184888,
                            ],
                            [
                                'lat' => 15.9008109,
                                'lon' => 120.6182259,
                            ],
                            [
                                'lat' => 15.900099,
                                'lon' => 120.617786,
                            ],
                            [
                                'lat' => 15.8996707,
                                'lon' => 120.6173837,
                            ],
                            [
                                'lat' => 15.8993302,
                                'lon' => 120.6167185,
                            ],
                            [
                                'lat' => 15.8989897,
                                'lon' => 120.6159085,
                            ],
                            [
                                'lat' => 15.8986853,
                                'lon' => 120.6145942,
                            ],
                            [
                                'lat' => 15.8986441,
                                'lon' => 120.6139827,
                            ],
                            [
                                'lat' => 15.8986905,
                                'lon' => 120.6135857,
                            ],
                            [
                                'lat' => 15.8988504,
                                'lon' => 120.6124753,
                            ],
                            [
                                'lat' => 15.898804,
                                'lon' => 120.6119925,
                            ],
                            [
                                'lat' => 15.8986389,
                                'lon' => 120.6116438,
                            ],
                            [
                                'lat' => 15.8979785,
                                'lon' => 120.6106836,
                            ],
                            [
                                'lat' => 15.897184,
                                'lon' => 120.609895,
                            ],
                            [
                                'lat' => 15.8963843,
                                'lon' => 120.6094176,
                            ],
                            [
                                'lat' => 15.8954556,
                                'lon' => 120.6092352,
                            ],
                            [
                                'lat' => 15.8949139,
                                'lon' => 120.6092781,
                            ],
                            [
                                'lat' => 15.8942896,
                                'lon' => 120.6094283,
                            ],
                            [
                                'lat' => 15.8934688,
                                'lon' => 120.6097372,
                            ],
                            [
                                'lat' => 15.8930411,
                                'lon' => 120.6097716,
                            ],
                            [
                                'lat' => 15.8926954,
                                'lon' => 120.6097287,
                            ],
                            [
                                'lat' => 15.8924091,
                                'lon' => 120.6095597,
                            ],
                            [
                                'lat' => 15.8918699,
                                'lon' => 120.6090474,
                            ],
                            [
                                'lat' => 15.8912043,
                                'lon' => 120.6081301,
                            ],
                            [
                                'lat' => 15.8907606,
                                'lon' => 120.6073683,
                            ],
                            [
                                'lat' => 15.8903272,
                                'lon' => 120.6064832,
                            ],
                            [
                                'lat' => 15.8897133,
                                'lon' => 120.6051421,
                            ],
                            [
                                'lat' => 15.8892334,
                                'lon' => 120.6041121,
                            ],
                            [
                                'lat' => 15.8887897,
                                'lon' => 120.6029749,
                            ],
                            [
                                'lat' => 15.8882944,
                                'lon' => 120.6017357,
                            ],
                            [
                                'lat' => 15.8881293,
                                'lon' => 120.6015104,
                            ],
                            [
                                'lat' => 15.8872883,
                                'lon' => 120.6010061,
                            ],
                            [
                                'lat' => 15.8870148,
                                'lon' => 120.6008613,
                            ],
                            [
                                'lat' => 15.8868291,
                                'lon' => 120.6007487,
                            ],
                            [
                                'lat' => 15.8867156,
                                'lon' => 120.6005287,
                            ],
                            [
                                'lat' => 15.8866279,
                                'lon' => 120.5999333,
                            ],
                            [
                                'lat' => 15.8865711,
                                'lon' => 120.5987075,
                            ],
                            [
                                'lat' => 15.8864937,
                                'lon' => 120.5979189,
                            ],
                            [
                                'lat' => 15.8864628,
                                'lon' => 120.5974013,
                            ],
                            [
                                'lat' => 15.8864086,
                                'lon' => 120.5966824,
                            ],
                            [
                                'lat' => 15.8864215,
                                'lon' => 120.5964035,
                            ],
                            [
                                'lat' => 15.8865308,
                                'lon' => 120.5960828,
                            ],
                            [
                                'lat' => 15.8866434,
                                'lon' => 120.5957141,
                            ],
                            [
                                'lat' => 15.8866717,
                                'lon' => 120.595462,
                            ],
                            [
                                'lat' => 15.8866485,
                                'lon' => 120.5950034,
                            ],
                            [
                                'lat' => 15.8865711,
                                'lon' => 120.5940378,
                            ],
                            [
                                'lat' => 15.8865247,
                                'lon' => 120.5930453,
                            ],
                        ],
                    ],
                    [
                        'type' => 'way',
                        'ref' => 1187078849,
                        'role' => 'outer',
                        'geometry' => [
                            [
                                'lat' => 15.9294283,
                                'lon' => 120.6241514,
                            ],
                            [
                                'lat' => 15.9092766,
                                'lon' => 120.6234068,
                            ],
                        ],
                    ],
                ],
                'tags' => [
                    'admin_level' => '6',
                    'boundary' => 'administrative',
                    'name' => 'Rosales',
                    'is_in:state' => 'Pangasinan',
                ],
            ],
        ],
    ];
}