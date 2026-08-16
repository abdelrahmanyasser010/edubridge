<?php

namespace App\Http\Resources\Assessment;

use App\Models\GradeAppeal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class GradeAppealResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof GradeAppeal) {
            throw new LogicException('GradeAppealResource expects a GradeAppeal model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'grade_entry_id' => (string) $this->resource->grade_entry_id,
            'student_id' => (string) $this->resource->student_id,
            'parent_id' => (string) $this->resource->parent_id,
            'reason' => $this->resource->reason,
            'status' => $this->resource->status,
            'review_note' => $this->resource->review_note,
            'reviewed_at' => $this->resource->reviewed_at === null ? null : Carbon::parse($this->resource->reviewed_at)->toJSON(),
        ];
    }
}
