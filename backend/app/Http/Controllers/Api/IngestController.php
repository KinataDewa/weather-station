<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorInstallation;
use App\Models\SensorCalibration;
use App\Models\SensorReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class IngestController extends Controller
{
    private function authenticateDevice(Request $request): ?Device
    {
        $deviceId = $request->input('device_id');
        $apiKey   = $request->header('X-Api-Key');

        $device = Device::where('device_id', $deviceId)
            ->whereIn('status', ['active', 'maintenance'])
            ->first();

        if (!$device || !Hash::check($apiKey, $device->api_key_hash)) {
            return null;
        }

        return $device;
    }

    private function processReading(Device $device, array $data): array
    {
        $ts       = Carbon::createFromTimestamp($data['ts'])->setTimezone('UTC');
        $now      = Carbon::now('UTC');
        $results  = [];

        // Tolak timestamp terlalu jauh di masa depan (> 2 jam)
        if ($ts->gt($now->copy()->addHours(2))) {
            return [['error' => 'timestamp_future', 'ts' => $data['ts']]];
        }

        foreach ($data['readings'] as $r) {
            $sensorCode = $r['s'];
            $rawValue   = $r['v'];

            // Cari sensor yang terpasang di device ini pada waktu ts
            $installation = SensorInstallation::where('device_id', $device->id)
                ->whereHas('sensor.sensorType', fn($q) => $q->where('code', $sensorCode))
                ->where('installed_at', '<=', $ts)
                ->where(fn($q) => $q->whereNull('removed_at')->orWhere('removed_at', '>=', $ts))
                ->with('sensor.sensorType', 'sensor.calibrations')
                ->first();

            if (!$installation) continue;

            $sensor     = $installation->sensor;
            $sensorType = $sensor->sensorType;

            // Quality flag — cek rentang valid
            $qualityFlag = true;
            $qualityNote = null;

            // Deteksi error code sensor (misal -999)
            if ($rawValue <= -999) {
                $qualityFlag = false;
                $qualityNote = 'sensor_error_code';
            } elseif (
                ($sensorType->min_value !== null && $rawValue < $sensorType->min_value) ||
                ($sensorType->max_value !== null && $rawValue > $sensorType->max_value)
            ) {
                $qualityFlag = false;
                $qualityNote = 'out_of_range';
            }

            // Kalibrasi — ambil yang berlaku pada waktu ts
            $calibration = $sensor->calibrations()
                ->where('valid_from', '<=', $ts)
                ->orderByDesc('valid_from')
                ->first();

            $calibratedValue = $calibration
                ? ($rawValue * $calibration->scale) + $calibration->offset
                : $rawValue;

            // Simpan — skip duplikat (unique: device_id + sensor_id + device_time)
            try {
                SensorReading::firstOrCreate(
                    [
                        'device_id'   => $device->id,
                        'sensor_id'   => $sensor->id,
                        'device_time' => $ts,
                    ],
                    [
                        'raw_value'        => $rawValue,
                        'calibrated_value' => $calibratedValue,
                        'quality_flag'     => $qualityFlag,
                        'quality_note'     => $qualityNote,
                        'server_time'      => $now,
                        'seq'              => $data['seq'] ?? null,
                    ]
                );
                $results[] = ['sensor' => $sensorCode, 'status' => 'ok'];
            } catch (\Exception $e) {
                $results[] = ['sensor' => $sensorCode, 'status' => 'duplicate'];
            }
        }

        return $results;
    }

    public function telemetry(Request $request)
    {
        $device = $this->authenticateDevice($request);
        if (!$device) {
            return response()->json([
                'success' => false,
                'code'    => 'DEVICE_UNAUTHORIZED',
                'message' => 'Device tidak dikenali atau tidak aktif',
            ], 401);
        }

        // Update health device
        $device->update([
            'last_seen_at'     => now(),
            'firmware_version' => $request->input('fw'),
            'battery_v'        => $request->input('battery_v'),
            'rssi'             => $request->input('rssi'),
        ]);

        $results = $this->processReading($device, $request->all());

        return response()->json([
            'success'    => true,
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'results'    => $results,
        ], 201);
    }

    public function batch(Request $request)
    {
        $device = $this->authenticateDevice($request);
        if (!$device) {
            return response()->json([
                'success' => false,
                'code'    => 'DEVICE_UNAUTHORIZED',
                'message' => 'Device tidak dikenali atau tidak aktif',
            ], 401);
        }

        $batch = $request->input('batch', []);

        // Batas maksimal 200 record per batch
        if (count($batch) > 200) {
            $batch = array_slice($batch, 0, 200);
        }

        $device->update(['last_seen_at' => now()]);

        $accepted  = 0;
        $duplicate = 0;
        $failed    = 0;
        $details   = [];

        foreach ($batch as $data) {
            $data['device_id'] = $request->input('device_id');
            $data['fw']        = $request->input('fw');
            $results           = $this->processReading($device, $data);

            foreach ($results as $r) {
                if (isset($r['error'])) { $failed++; }
                elseif ($r['status'] === 'duplicate') { $duplicate++; }
                else { $accepted++; }
            }
            $details[] = ['ts' => $data['ts'], 'results' => $results];
        }

        return response()->json([
            'success'    => true,
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'summary'    => [
                'total'     => count($batch),
                'accepted'  => $accepted,
                'duplicate' => $duplicate,
                'failed'    => $failed,
            ],
            'details'    => $details,
        ], 207);
    }

    public function heartbeat(Request $request)
    {
        $device = $this->authenticateDevice($request);
        if (!$device) {
            return response()->json([
                'success' => false,
                'code'    => 'DEVICE_UNAUTHORIZED',
                'message' => 'Device tidak dikenali atau tidak aktif',
            ], 401);
        }

        $device->update([
            'last_seen_at'     => now(),
            'firmware_version' => $request->input('fw'),
            'battery_v'        => $request->input('battery_v'),
            'rssi'             => $request->input('rssi'),
        ]);

        return response()->json([
            'success'    => true,
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'message'    => 'Heartbeat diterima',
        ]);
    }
}