<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Device;
use App\Models\Location;
use App\Models\DeviceStatusHistory;
use Illuminate\Support\Facades\Hash;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $locations = Location::all()->keyBy('name');

        $devices = [
            ['device_id' => 'WS-MLG-001', 'name' => 'Weather Station Malang Kota',  'location' => 'Stasiun Malang Kota'],
            ['device_id' => 'WS-BTU-001', 'name' => 'Weather Station Batu',          'location' => 'Stasiun Batu'],
            ['device_id' => 'WS-KPJ-001', 'name' => 'Weather Station Kepanjen',      'location' => 'Stasiun Kepanjen'],
        ];

        foreach ($devices as $d) {
            $device = Device::firstOrCreate(
                ['device_id' => $d['device_id']],
                [
                    'name'         => $d['name'],
                    'location_id'  => $locations[$d['location']]->id,
                    'status'       => 'active',
                    'api_key_hash' => Hash::make('secret-' . $d['device_id']),
                    'last_seen_at' => now(),
                ]
            );

            DeviceStatusHistory::firstOrCreate(
                ['device_id' => $device->id, 'status' => 'active'],
                ['changed_at' => now(), 'note' => 'Seeder']
            );
        }
    }
}