<?php

namespace App\Http\Resources\Academic;

use App\Models\ScheduleSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class ScheduleSlotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof ScheduleSlot) {
            throw new LogicException('ScheduleSlotResource expects a ScheduleSlot model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'academic_term_id' => (string) $this->resource->academic_term_id,
            'allocation_id' => (string) $this->resource->allocation_id,
            'weekday' => $this->resource->weekday,
            'starts_at' => substr($this->resource->starts_at, 0, 5),
            'ends_at' => substr($this->resource->ends_at, 0, 5),
            'room' => $this->resource->room,
            'status' => $this->resource->status,
        ];
    }
}
