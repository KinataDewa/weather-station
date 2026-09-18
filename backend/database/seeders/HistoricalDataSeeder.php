<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Device;
use App\Models\SensorInstallation;
use App\Models\SensorReading;
use Carbon\Carbon;

class HistoricalDataSeeder extends Seeder
{
    public function run(): void
    {
        $devices = Device::all();
        $days    = 7;
        $interval = 600; // detik

        foreach ($devices as $device) {
            $installations = SensorInstallation::where('device_id', $device->id)
                ->whereNull('removed_at')
                ->with('sensor.sensorType')
                ->get();

            $start = Carbon::now()->subDays($days)->startOfHour();
            $end   = Carbon::now();
            $current = $start->copy();

            $rainCounter = rand(100, 500);

            while ($current->lte($end)) {
                foreach ($installations as $inst) {
                    $code = $inst->sensor->sensorType->code;
                    $raw  = $this->generateValue($code, $current, $rainCounter);

                    if ($code === 'rain_counter') {
                        $rainCounter = $raw;
                    }

                    SensorReading::firstOrCreate(
                        [
                            'device_id'   => $device->id,
                            'sensor_id'   => $inst->sensor->id,
                            'device_time' => $current->copy()->utc(),
                        ],
                        [
                            'raw_value'        => $raw,
                            'calibrated_value' => $raw,
                            'quality_flag'     => true,
                            'server_time'      => $current->copy()->utc(),
                        ]
                    );
                }
                $current->addSeconds($interval);
            }
        }
    }

    private function generateValue(string $code, Carbon $time, int $rainCounter): float
    {
        $hour = $time->hour;

        return match($code) {
            'temp_air'     => round(22 + 8 * sin(($hour - 6) * M_PI / 12) + rand(-10, 10) / 10, 1),
            'humidity'     => round(min(100, max(40, 80 - 20 * sin(($hour - 6) * M_PI / 12) + rand(-50, 50) / 10)), 1),
            'pressure'     => round(1010 + rand(-30, 30) / 10, 1),
            'wind_speed'   => round(max(0, 3 + rand(-20, 30) / 10), 1),
            'wind_dir'     => rand(0, 359),
            'rain_counter' => $rainCounter + (rand(0, 10) > 8 ? rand(1, 5) : 0),
            'solar_rad'    => round(max(0, $hour >= 6 && $hour <= 18
                ? 800 * sin(($hour - 6) * M_PI / 12) + rand(-50, 50)
                : 0), 1),
            default        => 0,
        };
    }
}