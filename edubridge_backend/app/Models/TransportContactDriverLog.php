<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bus_route_id', 'driver_phone', 'outcome', 'notes', 'created_by_central_user_id'])]
class TransportContactDriverLog extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bus_route_id' => 'integer',
            'created_by_central_user_id' => 'integer',
        ];
    }
}
