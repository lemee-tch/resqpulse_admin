<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * A point inside each barangay (its "pole of inaccessibility", the
     * point farthest from its border), worked out from the official
     * boundaries in resources/data/rosales-barangays.geojson (item 59).
     * The earlier table was off for 25 of the 37 barangays — by up to
     * 1.6 km (Station District, Salvacion) — so pins were often named
     * after the wrong barangay. Used for the barangay labels on the maps,
     * the Add Center pin (evacuation.blade.php has the same table), and as
     * the last fallback when a pin is outside every boundary.
     */
    protected const BARANGAY_COORDINATES = [
        'Acop' => [15.871027, 120.656276],
        'Bakitbakit' => [15.899124, 120.656360],
        'Balingcanaway' => [15.883806, 120.657101],
        'Cabalaoangan Norte' => [15.882173, 120.627718],
        'Cabalaoangan Sur' => [15.874409, 120.627279],
        'Calanutan' => [15.860293, 120.647674],
        'Camangaan' => [15.843486, 120.633193],
        'Capitan Tomas' => [15.908639, 120.655709],
        'Carmay East' => [15.909438, 120.642675],
        'Carmay West' => [15.904790, 120.627777],
        'Carmen East' => [15.882926, 120.603633],
        'Carmen West' => [15.865548, 120.599287],
        'Casanicolasan' => [15.922814, 120.654723],
        'Coliling' => [15.856204, 120.612546],
        'Don Antonio Village' => [15.893489, 120.629279],
        'Guiling' => [15.843565, 120.615240],
        'Palakipak' => [15.866569, 120.628655],
        'Pangaoan' => [15.833010, 120.638903],
        'Rabago' => [15.855915, 120.632263],
        'Rizal' => [15.921771, 120.634742],
        'Salvacion' => [15.837710, 120.669686],
        'San Angel' => [15.850645, 120.645732],
        'San Antonio' => [15.842333, 120.646739],
        'San Bartolome' => [15.873866, 120.616820],
        'San Isidro' => [15.826038, 120.621537],
        'San Luis' => [15.832318, 120.653264],
        'San Pedro East' => [15.898519, 120.647249],
        'San Pedro West' => [15.891474, 120.643663],
        'San Vicente' => [15.836458, 120.680809],
        'Station District' => [15.882582, 120.636424],
        'Tomana East' => [15.898201, 120.622321],
        'Tomana West' => [15.887616, 120.613609],
        'Zone I (Poblacion)' => [15.888476, 120.627011],
        'Zone II (Poblacion)' => [15.891952, 120.631490],
        'Zone III (Poblacion)' => [15.895859, 120.627112],
        'Zone IV (Poblacion)' => [15.896214, 120.638706],
        'Zone V (Poblacion)' => [15.892078, 120.635513],
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
     * Full name => [lat, lng] for every barangay — a point inside it, for
     * anything that needs to PLACE a barangay on a map (the Map View's
     * labels), not just list their names (see names() above). The
     * barangays' real borders are in boundaries().
     */
    public static function allCoordinates(): array
    {
        return self::BARANGAY_COORDINATES;
    }

    /** The official barangay boundaries (item 59) — see boundaries(). */
    public const BOUNDARIES_FILE = 'data/rosales-barangays.geojson';

    /** @var array<string, array{bbox: array{float,float,float,float}, rings: list<list<array{float,float}>>}>|null */
    private static ?array $boundaries = null;

    /**
     * The 37 barangays' official borders (PSA and NAMRIA, PSGC 3Q 2026 —
     * OpenStreetMap has none for Rosales), from
     * resources/data/rosales-barangays.geojson: name => its outer rings
     * as [lng, lat] points, and a bounding box to skip far ones quickly.
     * Read once per request. Empty when the file is missing.
     */
    public static function boundaries(): array
    {
        if (self::$boundaries !== null) {
            return self::$boundaries;
        }

        self::$boundaries = [];
        $path = resource_path(self::BOUNDARIES_FILE);
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        foreach (($data['features'] ?? []) as $feature) {
            $name = $feature['properties']['name'] ?? null;
            $geometry = $feature['geometry'] ?? [];
            $polygons = match ($geometry['type'] ?? null) {
                'Polygon'      => [$geometry['coordinates']],
                'MultiPolygon' => $geometry['coordinates'],
                default        => [],
            };
            if (! $name || ! $polygons) {
                continue;
            }

            $rings = [];
            $lngs = [];
            $lats = [];
            foreach ($polygons as $polygon) {
                $outer = $polygon[0] ?? [];
                if (count($outer) < 3) {
                    continue;
                }
                $rings[] = $outer;
                foreach ($outer as [$lng, $lat]) {
                    $lngs[] = $lng;
                    $lats[] = $lat;
                }
            }
            if ($rings) {
                self::$boundaries[$name] = [
                    'bbox'  => [min($lngs), min($lats), max($lngs), max($lats)],
                    'rings' => $rings,
                ];
            }
        }

        return self::$boundaries;
    }

    /**
     * The barangay whose official border the pin is inside (item 59), or
     * null when it's outside all 37 — that is, outside Rosales.
     */
    public function barangayAt(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        foreach (self::boundaries() as $name => $boundary) {
            [$minLng, $minLat, $maxLng, $maxLat] = $boundary['bbox'];
            if ($lng < $minLng || $lng > $maxLng || $lat < $minLat || $lat > $maxLat) {
                continue;
            }
            foreach ($boundary['rings'] as $ring) {
                if ($this->inRing($lat, $lng, $ring)) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * The barangay an incident is in: from its GPS pin and the official
     * borders when it has one, otherwise the barangay named in its
     * location text. Null when neither says — or the text says it's
     * outside Rosales ("Outside Rosales: near Brgy. Carmen West, Rosales"
     * isn't in Carmen West).
     */
    public function barangayOf(?string $location, $lat, $lng): ?string
    {
        if (is_numeric($lat) && is_numeric($lng)) {
            $atPin = $this->barangayAt((float) $lat, (float) $lng);
            if ($atPin) {
                return $atPin;
            }
        }
        if (stripos((string) $location, 'outside rosales') !== false) {
            return null;
        }

        return $this->barangayFromText($location);
    }

    /** Ray casting: is the point inside this ring of [lng, lat] points? */
    private function inRing(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Turns a GPS pin into a readable place name — never raw coordinates.
     *
     *   inside Rosales:  "[landmark,] [building,] [street,] Brgy. <barangay>, Rosales, Pangasinan"
     *   outside Rosales: "Outside Rosales: [landmark,] <place, town, province>"
     *
     * e.g. "About 120 m from Rosales Central School, Rizal St, Brgy. Zone II
     * (Poblacion), Rosales, Pangasinan".
     *
     * The barangay (item 59) is the one whose official border the pin is
     * inside (barangayAt()) — outside all 37 means outside Rosales. Only
     * when the boundary file is missing: the Rosales boundary, the map
     * service's area names, then the nearest barangay centre.
     *
     * The landmark (item 59) is the nearest well-known place — a school,
     * church, barangay hall, market, gas station… — from Google Places
     * (landmark()). With $withLandmark false (the bulk Recheck locations)
     * Google isn't asked again: only a landmark already found for the spot
     * in the last 7 days is used.
     *
     * The building and street, best first:
     *   1. Google Geocoding — street-level address. Needs GOOGLE_MAPS_API_KEY
     *      with the Geocoding API enabled and no HTTP-referrer restriction
     *      (a browser-only Maps key is refused for server calls).
     *   2. OpenStreetMap Nominatim (free) — used when Google fails, for
     *      single new reports only ($allowOsm), never for bulk rechecks.
     */
    public function describe(?float $lat, ?float $lng, bool $allowOsm = true, bool $withLandmark = true): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $geo = $this->reverseGeocode($lat, $lng);
        if (! $geo && $allowOsm) {
            $geo = $this->reverseGeocodeOsm($lat, $lng);
        }

        // The official barangay borders decide (item 59).
        $barangay = $this->barangayAt($lat, $lng);
        $inside = $barangay !== null;

        if (! self::boundaries()) {
            // No boundary file: the Rosales boundary polygon, then what the
            // map service says, then distance to the barangay centres.
            $inside = $this->insideBoundary($lat, $lng);
            if ($inside === null) {
                $inside = ($geo && $geo['municipality'])
                    ? str_contains(strtolower($geo['municipality']), 'rosales')
                    : $this->nearestBarangay($lat, $lng) !== null;
            }
            foreach (($geo['areas'] ?? []) as $area) {
                $barangay = $this->barangayFromText($area);
                if ($barangay) {
                    break;
                }
            }
            if (! $barangay && $inside) {
                $barangay = $this->nearestBarangay($lat, $lng, 15.0);
            }
        }

        $landmark = $this->landmark($lat, $lng, $withLandmark);

        $parts = [];
        // Not twice when the pin is at the landmark itself.
        if ($landmark && mb_strtolower($landmark['name']) !== mb_strtolower((string) ($geo['place'] ?? ''))) {
            $parts[] = self::landmarkPhrase($landmark);
        }
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
            // Fits incidents.location (255) with "SOS Alert — " in front.
            'label'    => Str::limit($label, 235, '…'),
            'barangay' => $inside ? $barangay : null,
            'inside'   => $inside,
            'landmark' => $landmark,
            'source'   => $geo['source'] ?? 'offline',
        ];
    }

    /**
     * Readable place name for a GPS pin (see describe()). Kept for the
     * existing callers.
     */
    public function resolve(?float $lat, ?float $lng, bool $allowOsm = true, bool $withLandmark = true): ?string
    {
        return $this->describe($lat, $lng, $allowOsm, $withLandmark)['label'] ?? null;
    }

    // ── Landmarks (item 59) ───────────────────────────────────────────

    /**
     * Places that make good landmarks — Google Places (New) "Table A"
     * types. Responders from another barangay know these.
     */
    public const LANDMARK_TYPES = [
        'school', 'primary_school', 'secondary_school', 'university', 'church', 'mosque',
        'city_hall', 'local_government_office', 'courthouse', 'police', 'fire_station',
        'post_office', 'hospital', 'gas_station', 'bank', 'shopping_mall', 'supermarket',
        'market', 'plaza', 'park', 'bus_station', 'cemetery', 'stadium', 'sports_complex',
        'community_center', 'library',
    ];

    /** Smaller places: used when they're much nearer than a big one. */
    public const MINOR_LANDMARK_TYPES = [
        'preschool', 'pharmacy', 'drugstore', 'grocery_store', 'convenience_store',
        'playground', 'transit_station', 'fast_food_restaurant', 'hotel', 'hardware_store',
    ];

    /** No landmark farther than this from the pin. */
    public const LANDMARK_RADIUS_M = 500;

    /** Set for an hour when Google refuses landmark searches (see landmark()). */
    private const LANDMARKS_OFF_KEY = 'google_landmarks_off';

    /**
     * The nearest well-known place to a pin, within LANDMARK_RADIUS_M:
     * ['name' => 'Rosales Central School', 'meters' => 120, 'type' => 'school'],
     * or null. A small place (a convenience store, a fast-food branch…)
     * wins only when it's much nearer than a big one.
     *
     * From Google Places (New) Nearby Search, with GOOGLE_MAPS_API_KEY —
     * "Places API (New)" has to be turned on for that key in Google Cloud.
     * Cached for 7 days per spot (~11 m); with $search false only that
     * cache is read. When Google refuses (the API isn't on, no billing,
     * quota), landmarks are skipped for an hour — 5 minutes when it can't
     * be reached — and the location shows the street and barangay as
     * before.
     */
    public function landmark(float $lat, float $lng, bool $search = true): ?array
    {
        $apiKey = config('services.google.maps_key');
        if (empty($apiKey)) {
            return null;
        }

        $cacheKey = 'google_landmark_v1:' . round($lat, 4) . ',' . round($lng, 4);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return isset($cached['name']) ? $cached : null;
        }
        if (! $search || Cache::has(self::LANDMARKS_OFF_KEY)) {
            return null;
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(4)
                ->withHeaders([
                    'X-Goog-Api-Key'   => $apiKey,
                    'X-Goog-FieldMask' => 'places.displayName,places.location,places.types',
                ])
                ->post('https://places.googleapis.com/v1/places:searchNearby', [
                    'includedTypes'       => array_merge(self::LANDMARK_TYPES, self::MINOR_LANDMARK_TYPES),
                    'maxResultCount'      => 20,
                    'rankPreference'      => 'DISTANCE',
                    'languageCode'        => 'en',
                    'locationRestriction' => ['circle' => [
                        'center' => ['latitude' => $lat, 'longitude' => $lng],
                        'radius' => (float) self::LANDMARK_RADIUS_M,
                    ]],
                ]);
        } catch (\Throwable $e) {
            // Don't make every report wait for it.
            Log::warning('Google Places could not be reached — landmarks are skipped for 5 minutes.', ['error' => $e->getMessage()]);
            Cache::put(self::LANDMARKS_OFF_KEY, true, now()->addMinutes(5));

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Google Places refused the landmark search — landmarks are skipped for an hour. '
                . 'Turn on "Places API (New)" for GOOGLE_MAPS_API_KEY in Google Cloud.', [
                'status' => $response->status(),
                'error'  => $response->json('error.status'),
            ]);
            Cache::put(self::LANDMARKS_OFF_KEY, true, now()->addHour());

            return null;
        }

        $best = null;
        foreach (($response->json('places') ?? []) as $place) {
            $name = trim((string) ($place['displayName']['text'] ?? ''));
            $pLat = $place['location']['latitude'] ?? null;
            $pLng = $place['location']['longitude'] ?? null;
            if ($name === '' || ! is_numeric($pLat) || ! is_numeric($pLng)) {
                continue;
            }

            $types = (array) ($place['types'] ?? []);
            $major = (bool) array_intersect($types, self::LANDMARK_TYPES);
            $meters = (int) round($this->haversineKm($lat, $lng, (float) $pLat, (float) $pLng) * 1000);
            if ($meters > self::LANDMARK_RADIUS_M) {
                continue;
            }

            // A small place 2.5 times nearer than a big one wins.
            $score = $meters * ($major ? 1 : 2.5);
            if ($best === null || $score < $best['score']) {
                $kind = array_values(array_intersect($types, array_merge(self::LANDMARK_TYPES, self::MINOR_LANDMARK_TYPES)));
                $best = ['name' => Str::limit($name, 60, '…'), 'meters' => $meters, 'type' => $kind[0] ?? null, 'score' => $score];
            }
        }

        $result = $best ? ['name' => $best['name'], 'meters' => $best['meters'], 'type' => $best['type']] : ['none' => true];
        Cache::put($cacheKey, $result, now()->addDays(7));

        return $best ? $result : null;
    }

    /**
     * "Near Rosales Central School" (50 m or less), otherwise "About 120 m
     * from Rosales Central School" (to the nearest 10 m).
     */
    public static function landmarkPhrase(array $landmark): string
    {
        $meters = (int) ($landmark['meters'] ?? 0);

        return $meters <= 50
            ? "Near {$landmark['name']}"
            : 'About ' . (int) (round($meters / 10) * 10) . " m from {$landmark['name']}";
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

        // Inside one of the 37 official barangay borders (item 59).
        if (self::boundaries()) {
            return $this->barangayAt($lat, $lng) !== null;
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
    public function approximateSosLabel(?float $lat, ?float $lng, bool $allowOsm = true, bool $withLandmark = true): string
    {
        $resolved = $this->resolve($lat, $lng, $allowOsm, $withLandmark);

        return $resolved ? "SOS Alert — {$resolved}" : 'SOS Alert, Rosales';
    }

    /**
     * "Guest Report" prefixed variant for guest regular reports — same
     * GPS-pin-only situation as SOS (see Api\IncidentController::store()).
     */
    public function approximateReportLabel(?float $lat, ?float $lng, bool $allowOsm = true, bool $withLandmark = true): string
    {
        $resolved = $this->resolve($lat, $lng, $allowOsm, $withLandmark);

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