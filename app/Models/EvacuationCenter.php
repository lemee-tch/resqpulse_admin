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
     * Deliberately NOT tied into `occupancy` above — occupancy is a
     * manually-set live headcount the admin controls directly (see
     * EvacuationCenterController::updateStatus and the Add Center
     * form), while this log has no check-out step (see Evacuee model —
     * logging is arrival-only, by design). Auto-incrementing occupancy
     * from evacuee log entries would make it climb forever and never
     * reflect people who've actually left, which is worse than the
     * current manual number, not better.
     */
    public function evacuees()
    {
        return $this->hasMany(Evacuee::class);
    }

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