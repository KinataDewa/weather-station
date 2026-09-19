<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SensorReading;
use App\Models\ReadingAggregate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReadingController extends Controller
{
    public function latest($id)
    {
        $device = Device::with('location')->findOrFail($id);

        $readings = SensorReading::where('device_id', $device->id)
            ->with('sensor.sensorType')
            ->whereIn('id', function ($query) use ($device) {
                $query->selectRaw('MAX(id)')
                    ->from('sensor_readings')
                    ->where('device_id', $device->id)
                    ->groupBy('sensor_id');
            })
            ->get()
            ->keyBy(fn($r) => $r->sensor->sensorType->code);

        $sensorCodes = ['temp_air', 'humidity', 'pressure', 'wind_speed', 'wind_dir', 'solar_rad', 'rain_counter'];

        $values = [];
        foreach ($sensorCodes as $code) {
            $values[$code] = isset($readings[$code]) ? (float) $readings[$code]->calibrated_value : null;
        }

        $recordedAt = $readings->max(fn($r) => $r->device_time);

        $isOnline = $device->last_seen_at
            && Carbon::now()->diffInMinutes($device->last_seen_at) <= 15;

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => array_merge($values, [
                'recorded_at' => $recordedAt ? $recordedAt->setTimezone('Asia/Jakarta')->toIso8601String() : null,
                'device'      => [
                    'id'        => $device->id,
                    'name'      => $device->name,
                    'location'  => $device->location->name ?? null,
                    'is_online' => $isOnline,
                ],
            ]),
        ]);
    }

    public function index(Request $request)
    {
        $request->validate([
            'device_id'   => 'required|exists:devices,id',
            'sensor_type' => 'required|string',
            'from'        => 'required|date',
            'to'          => 'required|date',
            'interval'    => 'nullable|in:raw,1m,1h,1d',
            'agg'         => 'nullable|in:avg,min,max,sum',
        ]);

        $from     = Carbon::parse($request->from)->utc();
        $to       = Carbon::parse($request->to)->utc();
        $interval = $request->interval ?? 'raw';

        // Paksa agregasi kalau rentang > 7 hari dan interval raw
        if ($interval === 'raw' && $from->diffInDays($to) > 7) {
            $interval = '1h';
        }

        if ($interval === 'raw') {
            $data = SensorReading::where('device_id', $request->device_id)
                ->whereHas('sensor.sensorType', fn($q) => $q->where('code', $request->sensor_type))
                ->whereBetween('device_time', [$from, $to])
                ->where('quality_flag', true)
                ->orderBy('device_time')
                ->limit(1440) // maks 1 hari data per menit
                ->get()
                ->map(fn($r) => [
                    'ts'    => $r->device_time->setTimezone('Asia/Jakarta')->toIso8601String(),
                    'value' => $r->calibrated_value,
                ]);
        } else {
            $agg  = $request->agg ?? 'avg';
            $data = ReadingAggregate::where('device_id', $request->device_id)
                ->whereHas('sensor.sensorType', fn($q) => $q->where('code', $request->sensor_type))
                ->where('interval', $interval)
                ->whereBetween('bucket_time', [$from, $to])
                ->orderBy('bucket_time')
                ->limit(1000)
                ->get()
                ->map(fn($r) => [
                    'ts'    => $r->bucket_time->setTimezone('Asia/Jakarta')->toIso8601String(),
                    'value' => $r->{$agg . '_value'},
                ]);
        }

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'meta'       => [
                'interval' => $interval,
                'from'     => $from->setTimezone('Asia/Jakarta')->toIso8601String(),
                'to'       => $to->setTimezone('Asia/Jakarta')->toIso8601String(),
                'count'    => count($data),
            ],
            'data'       => $data,
        ]);
    }

    public function summary(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'date'      => 'nullable|date',
        ]);

        $date = Carbon::parse($request->date ?? today())->utc();
        $from = $date->copy()->startOfDay();
        $to   = $date->copy()->endOfDay();

        $aggregates = ReadingAggregate::where('device_id', $request->device_id)
            ->where('interval', '1d')
            ->whereBetween('bucket_time', [$from, $to])
            ->with('sensor.sensorType')
            ->get()
            ->keyBy(fn($r) => $r->sensor->sensorType->code);

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => [
                'temp_air' => [
                    'min' => $aggregates['temp_air']->min_value ?? null,
                    'max' => $aggregates['temp_air']->max_value ?? null,
                    'avg' => $aggregates['temp_air']->avg_value ?? null,
                ],
                'humidity' => [
                    'min' => $aggregates['humidity']->min_value ?? null,
                    'max' => $aggregates['humidity']->max_value ?? null,
                    'avg' => $aggregates['humidity']->avg_value ?? null,
                ],
                'rain_mm'      => isset($aggregates['rain_counter'])
                    ? $aggregates['rain_counter']->sum_value * 0.2
                    : null,
                'wind_speed_max' => $aggregates['wind_speed']->max_value ?? null,
            ],
        ]);
    }

    public function overview()
    {
        $devices = Device::with('location')
            ->where('status', '!=', 'decommissioned')
            ->get()
            ->map(function ($device) {
                $isOnline = $device->last_seen_at
                    && Carbon::now()->diffInMinutes($device->last_seen_at) <= 15;

                $latest = SensorReading::where('device_id', $device->id)
                    ->whereHas('sensor.sensorType', fn($q) => $q->whereIn('code', ['temp_air', 'humidity']))
                    ->whereIn('id', function ($query) use ($device) {
                        $query->selectRaw('MAX(id)')
                            ->from('sensor_readings')
                            ->where('device_id', $device->id)
                            ->groupBy('sensor_id');
                    })
                    ->with('sensor.sensorType')
                    ->get()
                    ->keyBy(fn($r) => $r->sensor->sensorType->code);

                return [
                    'id'           => $device->id,
                    'device_id'    => $device->device_id,
                    'name'         => $device->name,
                    'location'     => $device->location->name ?? null,
                    'status'       => $device->status,
                    'is_online'    => $isOnline,
                    'last_seen_at' => $device->last_seen_at
                        ? $device->last_seen_at->setTimezone('Asia/Jakarta')->toIso8601String()
                        : null,
                    'temp_air'  => $latest['temp_air']->calibrated_value ?? null,
                    'humidity'  => $latest['humidity']->calibrated_value ?? null,
                ];
            });

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $devices,
        ]);
    }
}