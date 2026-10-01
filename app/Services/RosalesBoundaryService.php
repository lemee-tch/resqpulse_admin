<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RosalesBoundaryService
{
    public const CACHE_KEY = 'rosales_boundary_and_barangays';

    public function getBoundaryData(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDays(30), function () {
            return $this->fetchBoundaryData();
        });
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function fetchBoundaryData(): array
    {
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
