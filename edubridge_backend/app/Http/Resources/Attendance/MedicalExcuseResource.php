<?php

namespace App\Http\Resources\Attendance;

use App\Models\MedicalExcuse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class MedicalExcuseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof MedicalExcuse) {
            throw new LogicException('MedicalExcuseResource expects a MedicalExcuse model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'student_id' => (string) $this->resource->student_id,
            'parent_id' => (string) $this->resource->parent_id,
            'file_id' => (string) $this->resource->file_id,
            'starts_on' => $this->resource->startsOnString(),
            'ends_on' => $this->resource->endsOnString(),
            'reason' => $this->resource->reason,
            'status' => $this->resource->status,
            'reviewed_by_central_user_id' => $this->resource->reviewed_by_central_user_id === null ? null : (string) $this->resource->reviewed_by_central_user_id,
            'reviewed_at' => $this->resource->reviewed_at === null ? null : Carbon::parse($this->resource->reviewed_at)->toJSON(),
            'review_note' => $this->resource->review_note,
        ];
    }
}
