<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            SensorTypeSeeder::class,
            DeviceSeeder::class,
            SensorSeeder::class,
            HistoricalDataSeeder::class,
        ]);
    }
}