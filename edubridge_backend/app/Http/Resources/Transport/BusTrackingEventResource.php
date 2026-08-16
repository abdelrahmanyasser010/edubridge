<?php

namespace App\Http\Resources\Transport;

use App\Models\BusTrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class BusTrackingEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof BusTrackingEvent) {
            throw new LogicException('BusTrackingEventResource expects a BusTrackingEvent model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'bus_trip_id' => (string) $this->resource->bus_trip_id,
            'latitude' => $this->resource->latitude,
            'longitude' => $this->resource->longitude,
            'speed_kph' => $this->resource->speed_kph,
            'recorded_at' => Carbon::parse($this->resource->recorded_at)->toJSON(),
        ];
    }
}
