<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvacuationCenter extends Model
{
    protected $fillable = [
        'name',
        'barangay',
        'latitude',
        'longitude',
        'capacity',
        'occupancy',
        'status',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'capacity'  => 'integer',
        'occupancy' => 'integer',
    ];

    // MDRRMO Operations Center / HQ — same coordinates used as the HQ label on the admin map view.
    protected const HQ_LAT = 15.8955;
    protected const HQ_LNG = 120.6265;

    /**
     * Straight-line distance from MDRRMO HQ, in kilometers (Haversine formula).
     * Computed on the fly so admins never have to type a distance by hand.
     */
    public function getDistanceFromHqAttribute(): ?float
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        $earthRadiusKm = 6371;

        $latDelta = deg2rad($this->latitude - self::HQ_LAT);
        $lngDelta = deg2rad($this->longitude - self::HQ_LNG);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad(self::HQ_LAT)) * cos(deg2rad($this->latitude)) * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 1);
    }

    public function getOccupancyPercentAttribute(): int
    {
        if (! $this->capacity) {
            return 0;
        }

        return (int) round(($this->occupancy / $this->capacity) * 100);
    }
}