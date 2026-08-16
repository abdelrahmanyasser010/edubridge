<?php

namespace App\Http\Resources\Academic;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class SectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Section) {
            throw new LogicException('SectionResource expects a Section model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'grade_level_id' => (string) $this->resource->grade_level_id,
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'room_number' => $this->resource->room_number,
            'capacity' => $this->resource->capacity,
            'homeroom_teacher_id' => $this->resource->homeroom_teacher_id === null ? null : (string) $this->resource->homeroom_teacher_id,
            'status' => $this->resource->status,
        ];
    }
}
