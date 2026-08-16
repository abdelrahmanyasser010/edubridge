<?php

namespace App\Http\Resources\Transport;

use App\Models\BusRouteAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class BusRouteAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof BusRouteAssignment) {
            throw new LogicException('BusRouteAssignmentResource expects a BusRouteAssignment model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'bus_route_id' => (string) $this->resource->bus_route_id,
            'student_id' => (string) $this->resource->student_id,
            'valid_from' => Carbon::parse($this->resource->valid_from)->toDateString(),
            'valid_until' => $this->resource->valid_until === null ? null : Carbon::parse($this->resource->valid_until)->toDateString(),
            'status' => $this->resource->status,
        ];
    }
}
