<?php

namespace App\Console\Commands;

use App\Models\ReadingAggregate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateReadings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'readings:aggregate';

    /**
     * The console command description.
     */
    protected $description = 'Hitung agregat avg/min/max/sum per jam (1h) dan per hari (1d) dari sensor_readings, lalu upsert ke reading_aggregates';

    /**
     * Interval agregasi yang dihitung -> unit date_trunc PostgreSQL.
     */
    private const INTERVALS = [
        '1h' => 'hour',
        '1d' => 'day',
    ];

    public function handle(): int
    {
        foreach (self::INTERVALS as $interval => $truncUnit) {
            $this->info("Menghitung agregat interval {$interval}...");

            $rows = DB::table('sensor_readings')
                ->selectRaw(<<<SQL
                    device_id,
                    sensor_id,
                    date_trunc('{$truncUnit}', device_time) as bucket_time,
                    AVG(calibrated_value) as avg_value,
                    MIN(calibrated_value) as min_value,
                    MAX(calibrated_value) as max_value,
                    SUM(calibrated_value) as sum_value,
                    COUNT(*) as reading_count
                SQL)
                ->where('quality_flag', true)
                ->whereNotNull('calibrated_value')
                ->groupBy('device_id', 'sensor_id', DB::raw("date_trunc('{$truncUnit}', device_time)"))
                ->get();

            if ($rows->isEmpty()) {
                $this->warn("Tidak ada data sensor_readings untuk interval {$interval}.");
                continue;
            }

            $now = now();
            $upsertRows = $rows->map(fn ($row) => [
                'device_id'   => $row->device_id,
                'sensor_id'   => $row->sensor_id,
                'interval'    => $interval,
                'bucket_time' => $row->bucket_time,
                'avg_value'   => round((float) $row->avg_value, 4),
                'min_value'   => round((float) $row->min_value, 4),
                'max_value'   => round((float) $row->max_value, 4),
                'sum_value'   => round((float) $row->sum_value, 4),
                'count'       => (int) $row->reading_count,
                'created_at'  => $now,
                'updated_at'  => $now,
            ])->all();

            $saved = 0;
            foreach (array_chunk($upsertRows, 500) as $chunk) {
                ReadingAggregate::upsert(
                    $chunk,
                    ['device_id', 'sensor_id', 'interval', 'bucket_time'],
                    ['avg_value', 'min_value', 'max_value', 'sum_value', 'count', 'updated_at'],
                );
                $saved += count($chunk);
            }

            $this->info("Selesai: {$saved} bucket untuk interval {$interval} disimpan/diupdate.");
        }

        $this->info('Agregasi selesai.');

        return self::SUCCESS;
    }
}
