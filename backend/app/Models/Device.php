<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'device_id', 'name', 'location_id', 'status',
        'api_key_hash', 'firmware_version', 'battery_v', 'rssi', 'last_seen_at'
    ];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function location() { return $this->belongsTo(Location::class); }
    public function statusHistories() { return $this->hasMany(DeviceStatusHistory::class); }
    public function sensorInstallations() { return $this->hasMany(SensorInstallation::class); }
    public function readings() { return $this->hasMany(SensorReading::class); }
}