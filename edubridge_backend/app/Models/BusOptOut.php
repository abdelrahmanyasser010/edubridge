<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'bus_route_id', 'parent_id', 'service_date', 'direction', 'reason'])]
class BusOptOut extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'bus_route_id' => 'integer',
            'parent_id' => 'integer',
            'service_date' => 'date:Y-m-d',
        ];
    }
}
