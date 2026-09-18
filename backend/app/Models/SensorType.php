<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SensorType extends Model
{
    protected $fillable = ['code', 'name', 'unit', 'min_value', 'max_value', 'precision'];

    public function sensors() { return $this->hasMany(Sensor::class); }
}