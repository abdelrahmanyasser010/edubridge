<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'capacity', 'driver_name', 'plate_number', 'driver_phone', 'supervisor_name', 'estimated_arrival_time', 'status'])]
class BusRoute extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }
}
