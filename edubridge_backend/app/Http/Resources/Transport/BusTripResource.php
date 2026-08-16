<?php

namespace App\Http\Resources\Transport;

use App\Models\BusTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class BusTripResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof BusTrip) {
            throw new LogicException('BusTripResource expects a BusTrip model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'bus_route_id' => (string) $this->resource->bus_route_id,
            'service_date' => Carbon::parse($this->resource->service_date)->toDateString(),
            'direction' => $this->resource->direction,
            'status' => $this->resource->status,
        ];
    }
}
