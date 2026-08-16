<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bus_trip_id', 'latitude', 'longitude', 'speed_kph', 'recorded_at'])]
class BusTrackingEvent extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bus_trip_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed_kph' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
