<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BarangayLocationService
{
    /**
     * How long a resolved coordinate's address is cached. SOS/report
     * coordinates from repeat testing or a stationary reporter hit the
     * same cache entry instead of re-hitting the billed Google Geocoding
     * endpoint every time.
     */
    protected const CACHE_TTL_HOURS = 24;

    /**
     * Beyond this distance from the closest barangay centroid, the pin
     * is treated as outside Rosales entirely (GPS drift, an emulator's
     * default location, testing from another town, etc.) — used only
     * for the offline fallback table below, when Google Geocoding is
     * unavailable. Rosales spans roughly ~10km edge-to-edge from HQ, so
     * 5km covers the municipality itself with some margin.
     */
    protected const MAX_BARANGAY_DISTANCE_KM = 5;

    /**
     * Offline fallback only — used when Google Geocoding can't be reached
     * at all (network failure, timeout, quota exceeded, missing/invalid
     * API key). Same verified centroid table used client-side in
     * evacuation.blade.php's BARANGAY_COORDINATES, kept in sync so the
     * approximation always matches what the Add Center dropdown would pin.
     */
    protected const BARANGAY_COORDINATES = [
        'Acop' => [15.867531, 120.652154],
        'Bakitbakit' => [15.878026, 120.650273],
        'Balingcanaway' => [15.892046, 120.651996],
        'Cabalaoangan Norte' => [15.884509, 120.626134],
        'Cabalaoangan Sur' => [15.876132, 120.633595],
        'Calanutan' => [15.864204, 120.633487],
        'Camangaan' => [15.849665, 120.635914],
        'Capitan Tomas' => [15.908782, 120.644112],
        'Carmay East' => [15.914305, 120.637743],
        'Carmay West' => [15.911984, 120.625233],
        'Carmen East' => [15.891492, 120.601517],
        'Carmen West' => [15.888648, 120.594245],
        'Casanicolasan' => [15.924801, 120.642405],
        'Coliling' => [15.854561, 120.620069],
        'Don Antonio Village' => [15.899285, 120.621318],
        'Guiling' => [15.849206, 120.621911],
        'Palakipak' => [15.862663, 120.617815],
        'Pangaoan' => [15.838011, 120.641362],
        'Rabago' => [15.856993, 120.634730],
        'Rizal' => [15.923404, 120.630220],
        'Salvacion' => [15.846088, 120.659706],
        'San Angel' => [15.860160, 120.656112],
        'San Antonio' => [15.853902, 120.658100],
        'San Bartolome' => [15.875859, 120.611195],
        'San Isidro' => [15.838424, 120.623979],
        'San Luis' => [15.842845, 120.650298],
        'San Pedro East' => [15.896897, 120.645530],
        'San Pedro West' => [15.896184, 120.636969],
        'San Vicente' => [15.847728, 120.671759],
        'Station District' => [15.892017, 120.620915],
        'Tomana East' => [15.895439, 120.613376],
        'Tomana West' => [15.892375, 120.608154],
        'Zone I (Poblacion)' => [15.888483, 120.623073],
        'Zone II (Poblacion)' => [15.897089, 120.629352],
        'Zone III (Poblacion)' => [15.904627, 120.621300],
        'Zone IV (Poblacion)' => [15.904928, 120.629206],
        'Zone V (Poblacion)' => [15.890244, 120.629699],
    ];

    /**
     * Public accessor for the 37 barangay names above, in the fixed order
     * they're declared in — the single source of truth for every "all
     * barangays" dropdown in the app (evacuation.blade.php, incident.blade.php,
     * evacuation-log.blade.php, report-analytics.blade.php's filter, etc.),
     * which previously each hardcoded their own copy of this same list.
     * That duplication is exactly what caused report-analytics.blade.php's
     * "Undefined variable $barangayList" in production: the one place this
     * list needed to be passed in from a controller had simply never been
     * updated to match. Call this instead of copy-pasting the array again.
     */
    public static function names(): array
    {
        return array_keys(self::BARANGAY_COORDINATES);
    }

    /**
     * Full name => [lat, lng] centroid map for every barangay — for
     * anything that needs to actually PLACE them on a map (the Map
     * View's barangay labels), not just list their names (see names()
     * above). Centroids only — there's no per-barangay border geometry
     * anywhere in this app, and none is available from OpenStreetMap
     * either: confirmed via a direct Overpass query scoped to Rosales'
     * real municipal relation that it has zero barangay-level
     * administrative boundaries mapped there at all. A label pinned at
     * this centroid is the best available placement, standing in for a
     * sub-boundary outline that simply doesn't exist yet in OSM.
     */
    public static function allCoordinates(): array
    {
        return self::BARANGAY_COORDINATES;
    }

    /**
     * Turns a GPS pin into a readable place name — never raw coordinates.
     *
     *   inside Rosales:  "[building,] [street,] Brgy. <barangay>, Rosales, Pangasinan"
     *   outside Rosales: "Outside Rosales: <place, town, province>"
     *
     * Where the details come from, best first:
     *   1. Google Geocoding — street-level address. Needs GOOGLE_MAPS_API_KEY
     *      with the Geocoding API enabled and no HTTP-referrer restriction
     *      (a browser-only Maps key is refused for server calls).
     *   2. OpenStreetMap Nominatim (free) — used when Google fails, for
     *      single new reports only ($allowOsm), never for bulk rechecks.
     *   3. Offline — the bundled Rosales boundary decides inside/outside,
     *      and the barangay is the one whose centre is nearest the pin.
     *      Always works, so a pin inside Rosales always gets a barangay.
     *
     * The barangay is taken from the map service when it names a real
     * Rosales barangay, otherwise from the nearest barangay centre (there
     * are no barangay border maps for Rosales — see allCoordinates()).
     */
    public function describe(?float $lat, ?float $lng, bool $allowOsm = true): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $geo = $this->reverseGeocode($lat, $lng);
        if (! $geo && $allowOsm) {
            $geo = $this->reverseGeocodeOsm($lat, $lng);
        }

        // Inside or outside Rosales: the real boundary polygon first, then
        // what the map service says, then distance to the barangay centres.
        $inside = $this->insideBoundary($lat, $lng);
        if ($inside === null) {
            $inside = ($geo && $geo['municipality'])
                ? str_contains(strtolower($geo['municipality']), 'rosales')
                : $this->nearestBarangay($lat, $lng) !== null;
        }

        $barangay = null;
        foreach (($geo['areas'] ?? []) as $area) {
            $barangay = $this->barangayFromText($area);
            if ($barangay) {
                break;
            }
        }
        if (! $barangay && $inside) {
            $barangay = $this->nearestBarangay($lat, $lng, 15.0);
        }

        $parts = [];
        if (! empty($geo['place'])) {
            $parts[] = $geo['place'];
        }
        if (! empty($geo['street'])) {
            $parts[] = $geo['street'];
        }

        if ($inside) {
            if ($barangay) {
                $parts[] = 'Brgy. ' . $barangay;
            }
            $parts[] = 'Rosales, Pangasinan';
            $label = $this->joinParts($parts);
        } else {
            foreach (['locality', 'municipality', 'province'] as $key) {
                if (! empty($geo[$key])) {
                    $parts[] = $geo[$key];
                }
            }
            if ($parts) {
                $label = 'Outside Rosales: ' . $this->joinParts($parts);
            } else {
                $near = $this->nearestBarangay($lat, $lng, 15.0);
                $label = $near ? "Outside Rosales: near Brgy. {$near}, Rosales" : 'Outside Rosales';
            }
        }

        return [
            'label'    => $label,
            'barangay' => $inside ? $barangay : null,
            'inside'   => $inside,
            'source'   => $geo['source'] ?? 'offline',
        ];
    }

    /**
     * Readable place name for a GPS pin (see describe()). Kept for the
     * existing callers.
     */
    public function resolve(?float $lat, ?float $lng, bool $allowOsm = true): ?string
    {
        return $this->describe($lat, $lng, $allowOsm)['label'] ?? null;
    }

    /**
     * The Rosales barangay named in a piece of text ("Carmay East",
     * "Poblacion Zone 3", "Zone Ⅲ", "Brgy. San Pedro West"...), or null.
     * Longest names are tried first so "San Pedro East" wins over shorter
     * look-alikes; "Zone 1" / "Zone I" / "Zone Ⅰ" all mean Zone I (Poblacion).
     */
    public function barangayFromText(?string $text): ?string
    {
        $text = mb_strtolower(trim((string) $text));
        if ($text === '') {
            return null;
        }

        // Unicode roman numerals (OpenStreetMap uses "Zone Ⅰ") and digits.
        $text = strtr($text, ['ⅰ' => 'i', 'ⅱ' => 'ii', 'ⅲ' => 'iii', 'ⅳ' => 'iv', 'ⅴ' => 'v',
                              'Ⅰ' => 'i', 'Ⅱ' => 'ii', 'Ⅲ' => 'iii', 'Ⅳ' => 'iv', 'Ⅴ' => 'v']);
        $roman = ['1' => 'i', '2' => 'ii', '3' => 'iii', '4' => 'iv', '5' => 'v'];
        $text = preg_replace_callback('/\bzone\s*([1-5])\b/u', fn ($m) => 'zone ' . $roman[$m[1]], $text);

        $names = array_keys(self::BARANGAY_COORDINATES);
        usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($names as $name) {
            $needle = mb_strtolower($name);
            if (str_contains($needle, '(poblacion)')) {
                $needle = trim(str_replace('(poblacion)', '', $needle));
            }
            if (preg_match('/\b' . preg_quote($needle, '/') . '\b/u', $text)) {
                return $name;
            }
        }

        return null;
    }

    /** "A, B, C" without empty or repeated pieces. */
    private function joinParts(array $parts): string
    {
        $seen = [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            $key = mb_strtolower($part);
            if ($part === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $part;
        }

        return implode(', ', $out);
    }

    /**
     * Whether a GPS pin actually falls within Rosales — used to require
     * admin approval before responders are dispatched for a report
     * genuinely outside the municipality (wrong pin, testing from
     * elsewhere, someone outside MDRRMO Rosales' jurisdiction), even
     * from a trusted logged-in citizen. Guest reports already require
     * approval regardless of location; this adds the same gate for
     * *anyone* once the location itself looks wrong.
     *
     * Order of precedence mirrors resolve() above, reusing the SAME
     * cached Google Geocoding call rather than a second network round-trip:
     *   1. Google's own municipality/county field, if it resolved
     *   2. Nearest-barangay-centroid distance, if Google didn't
     * Returns true (assume inside, don't gate) when NEITHER check can
     * produce an answer — a Geocoding outage should never block or
     * delay dispatch for what might be a completely genuine local
     * emergency; it fails open, not closed.
     */
    public function isWithinRosales(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) {
            // No coordinates to check (a logged-in citizen who typed an
            // address instead of dropping a pin) — nothing to gate on.
            return true;
        }

        // The real boundary polygon is authoritative: Google can label a
        // pin just past the line (river, border area) as "Rosales".
        $inside = $this->insideBoundary($lat, $lng);
        if ($inside !== null) {
            return $inside;
        }

        $geo = $this->reverseGeocode($lat, $lng);
        if ($geo && $geo['municipality']) {
            return str_contains(strtolower($geo['municipality']), 'rosales');
        }

        // Google didn't resolve at all (offline/quota/invalid key) — fall
        // back to the same offline barangay-distance table resolve()
        // uses for its own fallback.
        return $this->nearestBarangay($lat, $lng) !== null;
    }

    /**
     * True/false from the real Rosales boundary polygon (bundled OSM
     * relation), null if it can't be assembled.
     */
    private function insideBoundary(float $lat, float $lng): ?bool
    {
        try {
            $data = app(RosalesBoundaryService::class)->getBoundaryData();
            $ways = [];
            foreach (($data['elements'] ?? []) as $el) {
                if (($el['type'] ?? null) !== 'relation') {
                    continue;
                }
                foreach (($el['members'] ?? []) as $m) {
                    if (($m['role'] ?? '') === 'outer' && ! empty($m['geometry'])) {
                        $ways[] = array_map(fn ($g) => [$g['lat'], $g['lon']], $m['geometry']);
                    }
                }
            }
            if (count($ways) < 2) {
                return null;
            }

            // Chain the way segments end-to-end into one ring.
            $ring = array_shift($ways);
            while ($ways) {
                $end = end($ring);
                $found = false;
                foreach ($ways as $i => $w) {
                    if ($w[0] === $end) {
                        $ring = array_merge($ring, array_slice($w, 1));
                    } elseif (end($w) === $end) {
                        $ring = array_merge($ring, array_slice(array_reverse($w), 1));
                    } else {
                        continue;
                    }
                    unset($ways[$i]);
                    $found = true;
                    break;
                }
                if (! $found) {
                    return null;
                }
            }

            // Ray casting.
            $inside = false;
            $n = count($ring);
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                [$yi, $xi] = $ring[$i];
                [$yj, $xj] = $ring[$j];
                if ((($yi > $lat) !== ($yj > $lat))
                    && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                    $inside = ! $inside;
                }
            }

            return $inside;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * "SOS Alert" prefixed variant for SOS reports, which never carry
     * typed location text — only ever a GPS pin.
     */
    public function approximateSosLabel(?float $lat, ?float $lng, bool $allowOsm = true): string
    {
        $resolved = $this->resolve($lat, $lng, $allowOsm);

        return $resolved ? "SOS Alert — {$resolved}" : 'SOS Alert, Rosales';
    }

    /**
     * "Guest Report" prefixed variant for guest regular reports — same
     * GPS-pin-only situation as SOS (see Api\IncidentController::store()).
     */
    public function approximateReportLabel(?float $lat, ?float $lng, bool $allowOsm = true): string
    {
        $resolved = $this->resolve($lat, $lng, $allowOsm);

        return $resolved ? "Guest Report — {$resolved}" : 'Guest Report, Rosales';
    }

    /**
     * Calls the Google Geocoding API's reverse-geocoding endpoint for an
     * actual address (barangay/suburb + city, not just a centroid guess).
     * Cached for 24h per rounded coordinate pair so repeated reports from
     * the same spot don't hammer the billed endpoint. Returns null on any
     * failure — this must never block an incident/SOS submission.
     *
     * Returns the parsed pieces (locality/municipality/display_name)
     * rather than a pre-joined string, so both resolve() (display text)
     * and isWithinRosales() (boundary check) can reuse this ONE cached
     * call instead of each hitting Google separately.
     */
    private function reverseGeocode(float $lat, float $lng): ?array
    {
        // Round to ~11m precision for the cache key — enough to dedupe
        // GPS jitter from the same physical spot without merging
        // genuinely different locations.
        $cacheKey = 'google_reverse_geocode_v2:' . round($lat, 4) . ',' . round($lng, 4);

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($lat, $lng) {
            $apiKey = config('services.google.maps_key');

            if (empty($apiKey)) {
                Log::warning('Google Geocoding skipped: GOOGLE_MAPS_API_KEY is not configured', [
                    'lat' => $lat,
                    'lng' => $lng,
                ]);
                return null;
            }

            try {
                $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'latlng' => "{$lat},{$lng}",
                    'key'    => $apiKey,
                ]);

                if (! $response->successful()) {
                    Log::warning('Google Geocoding reverse lookup failed', [
                        'status' => $response->status(),
                        'lat'    => $lat,
                        'lng'    => $lng,
                    ]);
                    return null;
                }

                $data = $response->json();
                $status = $data['status'] ?? 'UNKNOWN_ERROR';

                // ZERO_RESULTS is a normal outcome (open water, way outside
                // any mapped address), not a failure worth logging loudly.
                if ($status === 'ZERO_RESULTS') {
                    return null;
                }

                if ($status !== 'OK') {
                    Log::warning('Google Geocoding reverse lookup returned a non-OK status', [
                        'status'       => $status,
                        'error_message' => $data['error_message'] ?? null,
                        'lat'          => $lat,
                        'lng'          => $lng,
                    ]);
                    return null;
                }

                // Google returns several results for one pin, most specific
                // first (street address, route, barangay/neighbourhood, town,
                // province). Walk them all: the building and street come from
                // the most specific ones, the area names from the rest.
                // Plus-code-only results ("7JFX+2C") are skipped.
                $place = null;
                $street = null;
                $locality = null;
                $municipality = null;
                $province = null;
                $areas = [];

                foreach (array_slice($data['results'] ?? [], 0, 8) as $result) {
                    if (in_array('plus_code', $result['types'] ?? [], true)) {
                        continue;
                    }

                    $byType = [];
                    foreach (($result['address_components'] ?? []) as $component) {
                        foreach (($component['types'] ?? []) as $type) {
                            $byType[$type] ??= $component['long_name'] ?? null;
                        }
                    }

                    $place ??= $byType['premise'] ?? $byType['establishment'] ?? $byType['point_of_interest'] ?? null;

                    if ($street === null && ! empty($byType['route']) && ! $this->isUnnamedRoad($byType['route'])) {
                        $street = trim(($byType['street_number'] ?? '') . ' ' . $byType['route']);
                    }

                    // In Philippine addresses Google puts the barangay in
                    // sublocality / neighborhood (when it knows it) and the
                    // municipality in locality.
                    $locality ??= $byType['sublocality_level_1']
                        ?? $byType['sublocality']
                        ?? $byType['neighborhood']
                        ?? $byType['administrative_area_level_5']
                        ?? null;

                    // Deliberately NOT defaulting to 'Rosales' — a pin in a
                    // neighbouring town must not be relabeled as Rosales.
                    $municipality ??= $byType['locality'] ?? $byType['administrative_area_level_3'] ?? null;
                    $province ??= $byType['administrative_area_level_2'] ?? $byType['administrative_area_level_1'] ?? null;

                    // Area names only — never streets or buildings, so "Rizal
                    // St" can't be mistaken for Barangay Rizal.
                    foreach (['sublocality_level_1', 'sublocality_level_2', 'sublocality', 'neighborhood',
                              'administrative_area_level_5', 'administrative_area_level_4', 'colloquial_area'] as $type) {
                        if (! empty($byType[$type])) {
                            $areas[] = $byType[$type];
                        }
                    }
                }

                // Don't show the town twice ("Rosales, Rosales").
                if ($locality !== null && $locality === $municipality) {
                    $locality = null;
                }
                if ($place !== null && $street !== null && str_contains($street, $place)) {
                    $place = null;
                }

                if ($place === null && $street === null && $locality === null && $municipality === null) {
                    return null;
                }

                return [
                    'source'       => 'google',
                    'place'        => $place,
                    'street'       => $street,
                    'locality'     => $locality,
                    'municipality' => $municipality,
                    'province'     => $province,
                    'areas'        => array_values(array_unique($areas)),
                    'display_name' => $data['results'][0]['formatted_address'] ?? null,
                ];
            } catch (\Throwable $e) {
                Log::warning('Google Geocoding reverse lookup threw an exception', [
                    'error' => $e->getMessage(),
                    'lat'   => $lat,
                    'lng'   => $lng,
                ]);
                return null;
            }
        });
    }

    /**
     * Free fallback when Google can't be used: OpenStreetMap's Nominatim
     * reverse geocoder. Same return shape as reverseGeocode(). Nominatim's
     * usage policy asks for an identifying User-Agent, at most one request
     * per second and caching — this is only called for single new reports
     * (never in bulk) and every answer is cached for 24 hours.
     */
    private function reverseGeocodeOsm(float $lat, float $lng): ?array
    {
        $cacheKey = 'osm_reverse_geocode:' . round($lat, 4) . ',' . round($lng, 4);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $contact = config('mail.from.address') ?: 'admin@resqpulse.com';
            // Short timeout: a new SOS waits for this before it's saved.
            $response = Http::timeout(4)
                ->withHeaders([
                    'User-Agent'      => "ResQPulse/1.0 (MDRRMO Rosales emergency reporting; {$contact})",
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat'            => $lat,
                    'lon'            => $lng,
                    'format'         => 'jsonv2',
                    'addressdetails' => 1,
                    'zoom'           => 18,
                ]);

            if (! $response->successful()) {
                Log::warning('OpenStreetMap reverse lookup failed', ['status' => $response->status(), 'lat' => $lat, 'lng' => $lng]);
                return null;
            }

            $data = $response->json();
            $address = $data['address'] ?? null;
            if (! is_array($address)) {
                return null;
            }

            $street = ! empty($address['road'])
                ? trim(($address['house_number'] ?? '') . ' ' . $address['road'])
                : null;

            // For a building/shop the top-level "name" is its name; for a
            // road it's just the road again.
            $place = $data['name'] ?? null;
            if ($place === '' || ($place !== null && $street !== null && str_contains($street, $place))) {
                $place = null;
            }

            $areas = array_values(array_filter([
                $address['village'] ?? null,
                $address['suburb'] ?? null,
                $address['quarter'] ?? null,
                $address['neighbourhood'] ?? null,
                $address['hamlet'] ?? null,
                $address['city_district'] ?? null,
            ]));

            $geo = [
                'source'       => 'osm',
                'place'        => $place,
                'street'       => $street,
                'locality'     => $areas[0] ?? null,
                'municipality' => $address['town'] ?? $address['city'] ?? $address['municipality'] ?? null,
                'province'     => $address['province'] ?? $address['state'] ?? $address['county'] ?? null,
                'areas'        => $areas,
                'display_name' => $data['display_name'] ?? null,
            ];

            Cache::put($cacheKey, $geo, now()->addHours(self::CACHE_TTL_HOURS));

            return $geo;
        } catch (\Throwable $e) {
            Log::warning('OpenStreetMap reverse lookup threw an exception', ['error' => $e->getMessage(), 'lat' => $lat, 'lng' => $lng]);
            return null;
        }
    }

    private function isUnnamedRoad(string $route): bool
    {
        return in_array(mb_strtolower(trim($route)), ['unnamed road', 'unnamed'], true);
    }

    /**
     * Nearest barangay name by straight-line (Haversine) distance from
     * the offline centroid table — only used when Google Geocoding itself
     * is unreachable. Returns null if the closest one is still farther
     * than MAX_BARANGAY_DISTANCE_KM away, since at that point the pin
     * almost certainly isn't in Rosales at all.
     */
    private function nearestBarangay(float $lat, float $lng, ?float $maxKm = null): ?string
    {
        $closest = null;
        $closestDistance = null;

        foreach (self::BARANGAY_COORDINATES as $name => [$bLat, $bLng]) {
            $distance = $this->haversineKm($lat, $lng, $bLat, $bLng);

            if ($closestDistance === null || $distance < $closestDistance) {
                $closestDistance = $distance;
                $closest = $name;
            }
        }

        if ($closestDistance === null || $closestDistance > ($maxKm ?? self::MAX_BARANGAY_DISTANCE_KM)) {
            return null;
        }

        return $closest;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}