<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ReadingAggregate extends Model
{
    protected $fillable = [
        'device_id', 'sensor_id', 'interval', 'bucket_time',
        'avg_value', 'min_value', 'max_value', 'sum_value', 'count'
    ];
    protected $casts = ['bucket_time' => 'datetime'];

    public function device() { return $this->belongsTo(Device::class); }
    public function sensor() { return $this->belongsTo(Sensor::class); }
}