<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name', 'latitude', 'longitude', 'altitude'];

    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}