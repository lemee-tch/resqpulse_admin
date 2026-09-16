<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BarangayLocationService
{
    /**
     * Nominatim reverse-geocoding zoom level — 16 resolves to
     * suburb/neighbourhood-level detail (close to barangay granularity)
     * rather than zooming out to city/province level.
     */
    protected const NOMINATIM_ZOOM = 16;

    /**
     * How long a resolved coordinate's address is cached. SOS/report
     * coordinates from repeat testing or a stationary reporter hit the
     * same cache entry instead of re-hitting the public Nominatim
     * endpoint every time.
     */
    protected const CACHE_TTL_HOURS = 24;

    /**
     * Beyond this distance from the closest barangay centroid, the pin
     * is treated as outside Rosales entirely (GPS drift, an emulator's
     * default location, testing from another town, etc.) — used only
     * for the offline fallback table below, when Nominatim itself is
     * unavailable. Rosales spans roughly ~10km edge-to-edge from HQ, so
     * 5km covers the municipality itself with some margin.
     */
    protected const MAX_BARANGAY_DISTANCE_KM = 5;

    /**
     * Offline fallback only — used when Nominatim can't be reached at
     * all (network failure, timeout, rate limit). Same verified centroid
     * table used client-side in evacuation.blade.php's
     * BARANGAY_COORDINATES, kept in sync so the approximation always
     * matches what the Add Center dropdown would pin.
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
     * Resolves a GPS pin to a real, human-readable location string.
     * Order of precedence:
     *   1. Nominatim reverse geocoding (actual street/area/barangay name)
     *   2. Nearest-barangay centroid table, if Nominatim fails and the
     *      pin is close enough to a known barangay to approximate
     *   3. Raw coordinates, if both of the above come up empty
     */
    public function resolve(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $geo = $this->reverseGeocode($lat, $lng);
        if ($geo) {
            if ($geo['locality']) {
                return $geo['municipality']
                    ? "{$geo['locality']}, {$geo['municipality']}"
                    : $geo['locality'];
            }
            if ($geo['display_name']) {
                return $geo['display_name'];
            }
        }

        $barangay = $this->nearestBarangay($lat, $lng);
        if ($barangay) {
            return "Approx. {$barangay}, Rosales";
        }

        return 'Lat ' . round($lat, 7) . ', Lng ' . round($lng, 7);
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
     * cached Nominatim call rather than a second network round-trip:
     *   1. Nominatim's own municipality/county field, if it resolved
     *   2. Nearest-barangay-centroid distance, if Nominatim didn't
     * Returns true (assume inside, don't gate) when NEITHER check can
     * produce an answer — a Nominatim outage should never block or
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

        $geo = $this->reverseGeocode($lat, $lng);
        if ($geo && $geo['municipality']) {
            return str_contains(strtolower($geo['municipality']), 'rosales');
        }

        // Nominatim didn't resolve at all (offline/rate-limited) — fall
        // back to the same offline barangay-distance table resolve()
        // uses for its own fallback.
        return $this->nearestBarangay($lat, $lng) !== null;
    }

    /**
     * "SOS Alert" prefixed variant for SOS reports, which never carry
     * typed location text — only ever a GPS pin.
     */
    public function approximateSosLabel(?float $lat, ?float $lng): string
    {
        $resolved = $this->resolve($lat, $lng);

        return $resolved ? "SOS Alert — {$resolved}" : 'SOS Alert, Rosales';
    }

    /**
     * "Guest Report" prefixed variant for guest regular reports — same
     * GPS-pin-only situation as SOS (see Api\IncidentController::store()).
     */
    public function approximateReportLabel(?float $lat, ?float $lng): string
    {
        $resolved = $this->resolve($lat, $lng);

        return $resolved ? "Guest Report — {$resolved}" : 'Guest Report, Rosales';
    }

    /**
     * Calls Nominatim's reverse-geocoding endpoint for an actual address
     * (barangay/suburb + city, not just a centroid guess). Cached for
     * 24h per rounded coordinate pair so repeated reports from the same
     * spot don't hammer the public endpoint. Returns null on any
     * failure — this must never block an incident/SOS submission.
     *
     * Returns the parsed pieces (locality/municipality/display_name)
     * rather than a pre-joined string, so both resolve() (display text)
     * and isWithinRosales() (boundary check) can reuse this ONE cached
     * call instead of each hitting Nominatim separately.
     */
    private function reverseGeocode(float $lat, float $lng): ?array
    {
        // Round to ~11m precision for the cache key — enough to dedupe
        // GPS jitter from the same physical spot without merging
        // genuinely different locations.
        $cacheKey = 'nominatim_reverse:' . round($lat, 4) . ',' . round($lng, 4);

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($lat, $lng) {
            try {
                $response = Http::withHeaders([
                    // Nominatim's usage policy requires an identifying
                    // User-Agent on every request.
                    'User-Agent' => 'ResQPulse-MDRRMO-Rosales/1.0',
                ])->timeout(8)->get('https://nominatim.openstreetmap.org/reverse', [
                    'format'         => 'jsonv2',
                    'lat'            => $lat,
                    'lon'            => $lng,
                    'zoom'           => self::NOMINATIM_ZOOM,
                    'addressdetails' => 1,
                ]);

                if (! $response->successful()) {
                    Log::warning('Nominatim reverse geocode failed', [
                        'status' => $response->status(),
                        'lat'    => $lat,
                        'lng'    => $lng,
                    ]);
                    return null;
                }

                $data = $response->json();
                $addr = $data['address'] ?? null;

                if (! $addr) {
                    return null;
                }

                // Prefer the most specific locality field Nominatim gives
                // us — barangay-equivalent tags first, falling back to
                // broader ones.
                $locality = $addr['village']
                    ?? $addr['suburb']
                    ?? $addr['neighbourhood']
                    ?? $addr['quarter']
                    ?? $addr['town']
                    ?? $addr['city_district']
                    ?? null;

                // Deliberately NOT defaulting this to 'Rosales' when
                // Nominatim doesn't return municipality/city/county —
                // that produced a real bug: a pin in Vacante (an actual
                // barangay, but of Binalonan, not Rosales) got silently
                // labeled "Vacante, Rosales" and then passed
                // isWithinRosales() because the fabricated municipality
                // obviously "contained Rosales." Falling back to the
                // province is honest about what Nominatim actually told
                // us; null (unknown) is used downstream to fall through
                // to the barangay-distance check instead of assuming.
                $municipality = $addr['municipality'] ?? $addr['city'] ?? $addr['county'] ?? $addr['state'] ?? null;

                return [
                    'locality'     => $locality,
                    'municipality' => $municipality,
                    'display_name' => $data['display_name'] ?? null,
                ];
            } catch (\Throwable $e) {
                Log::warning('Nominatim reverse geocode threw an exception', [
                    'error' => $e->getMessage(),
                    'lat'   => $lat,
                    'lng'   => $lng,
                ]);
                return null;
            }
        });
    }

    /**
     * Nearest barangay name by straight-line (Haversine) distance from
     * the offline centroid table — only used when Nominatim itself is
     * unreachable. Returns null if the closest one is still farther than
     * MAX_BARANGAY_DISTANCE_KM away, since at that point the pin almost
     * certainly isn't in Rosales at all.
     */
    private function nearestBarangay(float $lat, float $lng): ?string
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

        if ($closestDistance === null || $closestDistance > self::MAX_BARANGAY_DISTANCE_KM) {
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