<?php

namespace App\Http\Resources\Academic;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class SubjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Subject) {
            throw new LogicException('SubjectResource expects a Subject model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'weekly_periods' => $this->resource->pivot?->weekly_periods === null ? null : (int) $this->resource->pivot->weekly_periods,
            'status' => $this->resource->status,
        ];
    }
}
