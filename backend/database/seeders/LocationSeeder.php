<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['name' => 'Stasiun Malang Kota', 'latitude' => -7.9797, 'longitude' => 112.6304, 'altitude' => 445],
            ['name' => 'Stasiun Batu', 'latitude' => -7.8714, 'longitude' => 112.5261, 'altitude' => 871],
            ['name' => 'Stasiun Kepanjen', 'latitude' => -8.1286, 'longitude' => 112.5717, 'altitude' => 334],
        ];

        foreach ($locations as $loc) {
            Location::firstOrCreate(['name' => $loc['name']], $loc);
        }
    }
}