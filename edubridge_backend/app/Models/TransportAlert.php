<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bus_route_id', 'bus_trip_id', 'type', 'message', 'delay_minutes', 'channels', 'created_by_central_user_id'])]
class TransportAlert extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bus_route_id' => 'integer',
            'bus_trip_id' => 'integer',
            'delay_minutes' => 'integer',
            'channels' => 'array',
            'created_by_central_user_id' => 'integer',
        ];
    }
}
