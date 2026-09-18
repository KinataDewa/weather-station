<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SensorType;

class SensorTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'temp_air',     'name' => 'Suhu Udara',           'unit' => '°C',   'min_value' => -40, 'max_value' => 80,    'precision' => 1],
            ['code' => 'humidity',     'name' => 'Kelembapan',            'unit' => '%',    'min_value' => 0,   'max_value' => 100,   'precision' => 1],
            ['code' => 'pressure',     'name' => 'Tekanan Udara',         'unit' => 'hPa',  'min_value' => 300, 'max_value' => 1100,  'precision' => 1],
            ['code' => 'wind_speed',   'name' => 'Kecepatan Angin',       'unit' => 'm/s',  'min_value' => 0,   'max_value' => 100,   'precision' => 1],
            ['code' => 'wind_dir',     'name' => 'Arah Angin',            'unit' => '°',    'min_value' => 0,   'max_value' => 359,   'precision' => 0],
            ['code' => 'rain_counter', 'name' => 'Penghitung Curah Hujan','unit' => 'tips', 'min_value' => 0,   'max_value' => 99999, 'precision' => 0],
            ['code' => 'solar_rad',    'name' => 'Radiasi Matahari',      'unit' => 'W/m²', 'min_value' => 0,   'max_value' => 1500,  'precision' => 1],
        ];

        foreach ($types as $type) {
            SensorType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}