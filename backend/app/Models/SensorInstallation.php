<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SensorInstallation extends Model
{
    protected $fillable = ['sensor_id', 'device_id', 'installed_at', 'removed_at'];
    protected $casts = ['installed_at' => 'datetime', 'removed_at' => 'datetime'];

    public function sensor() { return $this->belongsTo(Sensor::class); }
    public function device() { return $this->belongsTo(Device::class); }
}