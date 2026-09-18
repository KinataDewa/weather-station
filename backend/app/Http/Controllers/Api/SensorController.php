<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Models\SensorCalibration;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SensorController extends Controller
{
    public function index()
    {
        $sensors = Sensor::with('sensorType')->get();

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $sensors,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'serial_number'  => 'required|string|unique:sensors,serial_number',
            'sensor_type_id' => 'required|exists:sensor_types,id',
        ]);

        $sensor = Sensor::create($request->all());

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $sensor->load('sensorType'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $sensor = Sensor::findOrFail($id);
        $sensor->update($request->only(['serial_number']));

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $sensor->fresh('sensorType'),
        ]);
    }

    public function destroy($id)
    {
        $sensor = Sensor::findOrFail($id);
        $sensor->delete();

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'message'    => 'Sensor berhasil dihapus',
        ]);
    }

    public function calibrations($id)
    {
        $sensor        = Sensor::findOrFail($id);
        $calibrations  = $sensor->calibrations()->orderByDesc('valid_from')->get();

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $calibrations,
        ]);
    }

    public function addCalibration(Request $request, $id)
    {
        $request->validate([
            'offset'     => 'nullable|numeric',
            'scale'      => 'nullable|numeric',
            'valid_from' => 'required|date',
            'note'       => 'nullable|string',
        ]);

        $sensor        = Sensor::findOrFail($id);
        $calibration   = $sensor->calibrations()->create([
            'offset'     => $request->offset ?? 0,
            'scale'      => $request->scale ?? 1,
            'valid_from' => $request->valid_from,
            'note'       => $request->note,
        ]);

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $calibration,
        ], 201);
    }
}