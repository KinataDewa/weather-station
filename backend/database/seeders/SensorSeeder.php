<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\SensorInstallation;
use App\Models\Device;

class SensorSeeder extends Seeder
{
    public function run(): void
    {
        $types   = SensorType::all()->keyBy('code');
        $devices = Device::all();

        foreach ($devices as $device) {
            foreach ($types as $code => $type) {
                $serial = 'SN-' . strtoupper($code) . '-' . $device->device_id;

                $sensor = Sensor::firstOrCreate(
                    ['serial_number' => $serial],
                    ['sensor_type_id' => $type->id]
                );

                SensorInstallation::firstOrCreate(
                    ['sensor_id' => $sensor->id, 'device_id' => $device->id],
                    ['installed_at' => now()->subDays(30)]
                );
            }
        }
    }
}