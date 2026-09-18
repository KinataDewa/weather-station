<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sensor extends Model
{
    use SoftDeletes;

    protected $fillable = ['serial_number', 'sensor_type_id'];

    public function sensorType() { return $this->belongsTo(SensorType::class); }
    public function installations() { return $this->hasMany(SensorInstallation::class); }
    public function calibrations() { return $this->hasMany(SensorCalibration::class); }
    public function readings() { return $this->hasMany(SensorReading::class); }
}