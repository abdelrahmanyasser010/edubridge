<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bus_route_id', 'service_date', 'direction', 'status', 'started_at', 'ended_at'])]
class BusTrip extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bus_route_id' => 'integer',
            'service_date' => 'date:Y-m-d',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
