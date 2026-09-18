<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SensorType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SensorTypeController extends Controller
{
    public function index()
    {
        $types = SensorType::all();

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $types,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'      => 'required|string|unique:sensor_types,code',
            'name'      => 'required|string',
            'unit'      => 'required|string',
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
            'precision' => 'nullable|integer',
        ]);

        $type = SensorType::create($request->all());

        return response()->json([
            'success'    => true,
            'request_id' => (string) Str::uuid(),
            'data'       => $type,
        ], 201);
    }
}