<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['device_id', 'status', 'note', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}