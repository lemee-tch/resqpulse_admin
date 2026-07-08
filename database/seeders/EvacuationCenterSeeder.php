<?php

namespace Database\Seeders;

use App\Models\EvacuationCenter;
use Illuminate\Database\Seeder;

class EvacuationCenterSeeder extends Seeder
{
    /**
     * Seeds the two real, currently-known evacuation centers
     * (matching what the citizen app already shows).
     * Capacity is an estimate — edit it from the admin panel once you
     * have the actual figures from MDRRMO.
     */
    public function run(): void
    {
        $centers = [
            [
                'name'      => 'Rosales Municipal Gymnasium',
                'barangay'  => 'Poblacion',
                'latitude'  => 15.893130602709453,
                'longitude' => 120.6325274234427,
                'capacity'  => 500,
                'occupancy' => 0,
                'status'    => 'open',
            ],
            [
                'name'      => 'San Pedro East - Elementary School',
                'barangay'  => 'San Pedro East',
                'latitude'  => 15.898432,
                'longitude' => 120.648095,
                'capacity'  => 350,
                'occupancy' => 0,
                'status'    => 'open',
            ],
        ];

        foreach ($centers as $center) {
            EvacuationCenter::updateOrCreate(
                ['name' => $center['name']],
                $center
            );
        }
    }
}