<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SensorCalibration extends Model
{
    protected $fillable = ['sensor_id', 'offset', 'scale', 'valid_from', 'note'];
    protected $casts = ['valid_from' => 'datetime'];

    public function sensor() { return $this->belongsTo(Sensor::class); }
}