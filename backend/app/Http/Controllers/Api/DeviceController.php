<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceStatusHistory;
use App\Models\Location;
use App\Models\Sensor;
use App\Models\SensorInstallation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::with('location')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->location_id, fn($q) => $q->where('location_id', $request->location_id))
            ->when($request->q, fn($q) => $q->where('name', 'like', "%{$request->q}%"));

        $devices = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $devices,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'device_id'   => 'required|string|unique:devices,device_id',
            'name'        => 'required|string',
            'location_id' => 'required|exists:locations,id',
        ]);

        // Generate API key
        $rawKey = Str::random(64);

        $device = Device::create([
            'device_id'    => $request->device_id,
            'name'         => $request->name,
            'location_id'  => $request->location_id,
            'status'       => 'provisioned',
            'api_key_hash' => Hash::make($rawKey),
        ]);

        // Catat history status
        DeviceStatusHistory::create([
            'device_id'  => $device->id,
            'status'     => 'provisioned',
            'note'       => 'Device baru didaftarkan',
            'changed_at' => now(),
        ]);

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $device,
            'api_key'    => $rawKey, // hanya muncul sekali
        ], 201);
    }

    public function show($id)
    {
        $device = Device::with('location', 'sensorInstallations.sensor.sensorType')
            ->findOrFail($id);

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $device,
        ]);
    }

    public function update(Request $request, $id)
    {
        $device = Device::findOrFail($id);

        $allowed = ['name', 'location_id', 'status'];
        $data    = $request->only($allowed);

        // Catat perubahan status
        if (isset($data['status']) && $data['status'] !== $device->status) {
            DeviceStatusHistory::create([
                'device_id'  => $device->id,
                'status'     => $data['status'],
                'note'       => $request->note ?? null,
                'changed_at' => now(),
            ]);
        }

        $device->update($data);

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $device->fresh('location'),
        ]);
    }

    public function destroy($id)
    {
        $device = Device::findOrFail($id);
        $device->update(['status' => 'decommissioned']);

        DeviceStatusHistory::create([
            'device_id'  => $device->id,
            'status'     => 'decommissioned',
            'note'       => 'Device dihapus via API',
            'changed_at' => now(),
        ]);

        $device->delete(); // soft delete

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'message'    => 'Device berhasil dihapus',
        ]);
    }

    public function health($id)
    {
        $device  = Device::findOrFail($id);
        $offline = $device->last_seen_at
            ? Carbon::now()->diffInMinutes($device->last_seen_at)
            : null;

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => [
                'device_id'        => $device->device_id,
                'status'           => $device->status,
                'last_seen_at'     => $device->last_seen_at,
                'minutes_offline'  => $offline,
                'is_online'        => $offline !== null && $offline <= 15,
                'battery_v'        => $device->battery_v,
                'rssi'             => $device->rssi,
                'firmware_version' => $device->firmware_version,
            ],
        ]);
    }

    public function rotateCredentials($id)
    {
        $device = Device::findOrFail($id);
        $rawKey = Str::random(64);

        $device->update(['api_key_hash' => Hash::make($rawKey)]);

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'api_key'    => $rawKey, // hanya muncul sekali
        ]);
    }

    public function attachSensor(Request $request, $id)
    {
        $request->validate([
            'sensor_id'    => 'required|exists:sensors,id',
            'installed_at' => 'nullable|date',
        ]);

        $device = Device::findOrFail($id);

        // Lepas dulu kalau sensor masih terpasang di device lain
        SensorInstallation::where('sensor_id', $request->sensor_id)
            ->whereNull('removed_at')
            ->update(['removed_at' => now()]);

        $installation = SensorInstallation::create([
            'sensor_id'    => $request->sensor_id,
            'device_id'    => $device->id,
            'installed_at' => $request->installed_at ?? now(),
        ]);

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $installation,
        ], 201);
    }

    public function detachSensor($id, $sensorId)
    {
        $installation = SensorInstallation::where('device_id', $id)
            ->where('sensor_id', $sensorId)
            ->whereNull('removed_at')
            ->firstOrFail();

        $installation->update(['removed_at' => now()]);

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'message'    => 'Sensor berhasil dilepas',
        ]);
    }
}