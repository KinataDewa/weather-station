<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\SensorTypeController;
use App\Http\Controllers\Api\SensorController;
use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\ReadingController;

// Ingestion (dipanggil device)
Route::post('/ingest/telemetry', [IngestController::class, 'telemetry']);
Route::post('/ingest/telemetry/batch', [IngestController::class, 'batch']);
Route::post('/ingest/heartbeat', [IngestController::class, 'heartbeat']);

// Device management
Route::get('/devices', [DeviceController::class, 'index']);
Route::post('/devices', [DeviceController::class, 'store']);
Route::get('/devices/{id}', [DeviceController::class, 'show']);
Route::patch('/devices/{id}', [DeviceController::class, 'update']);
Route::delete('/devices/{id}', [DeviceController::class, 'destroy']);
Route::get('/devices/{id}/health', [DeviceController::class, 'health']);
Route::post('/devices/{id}/credentials/rotate', [DeviceController::class, 'rotateCredentials']);
Route::post('/devices/{id}/sensors', [DeviceController::class, 'attachSensor']);
Route::delete('/devices/{id}/sensors/{sensorId}', [DeviceController::class, 'detachSensor']);
Route::get('/devices/{id}/readings/latest', [ReadingController::class, 'latest']);

// Sensor management
Route::get('/sensor-types', [SensorTypeController::class, 'index']);
Route::post('/sensor-types', [SensorTypeController::class, 'store']);
Route::get('/sensors', [SensorController::class, 'index']);
Route::post('/sensors', [SensorController::class, 'store']);
Route::patch('/sensors/{id}', [SensorController::class, 'update']);
Route::delete('/sensors/{id}', [SensorController::class, 'destroy']);
Route::get('/sensors/{id}/calibrations', [SensorController::class, 'calibrations']);
Route::post('/sensors/{id}/calibrations', [SensorController::class, 'addCalibration']);

// Query data
Route::get('/readings', [ReadingController::class, 'index']);
Route::get('/readings/summary', [ReadingController::class, 'summary']);
Route::get('/dashboard/overview', [ReadingController::class, 'overview']);