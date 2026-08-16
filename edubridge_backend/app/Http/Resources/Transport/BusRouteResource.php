<?php

namespace App\Http\Resources\Transport;

use App\Models\BusRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class BusRouteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof BusRoute) {
            throw new LogicException('BusRouteResource expects a BusRoute model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'capacity' => $this->resource->capacity,
            'driver_name' => $this->resource->driver_name,
            'status' => $this->resource->status,
        ];
    }
}
