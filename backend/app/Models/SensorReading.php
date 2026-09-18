<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SensorReading extends Model
{
    protected $fillable = [
        'device_id', 'sensor_id', 'raw_value', 'calibrated_value',
        'quality_flag', 'quality_note', 'device_time', 'server_time', 'seq'
    ];
    protected $casts = ['device_time' => 'datetime', 'server_time' => 'datetime', 'quality_flag' => 'boolean'];

    public function device() { return $this->belongsTo(Device::class); }
    public function sensor() { return $this->belongsTo(Sensor::class); }
}