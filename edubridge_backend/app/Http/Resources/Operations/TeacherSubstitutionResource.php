<?php

namespace App\Http\Resources\Operations;

use App\Models\TeacherSubstitution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class TeacherSubstitutionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof TeacherSubstitution) {
            throw new LogicException('TeacherSubstitutionResource expects a TeacherSubstitution model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'teaching_session_id' => (string) $this->resource->teaching_session_id,
            'original_teacher_id' => (string) $this->resource->original_teacher_id,
            'substitute_teacher_id' => (string) $this->resource->substitute_teacher_id,
            'reason' => $this->resource->reason,
            'status' => $this->resource->status,
            'response_note' => $this->resource->response_note,
            'responded_at' => $this->resource->responded_at === null ? null : Carbon::parse($this->resource->responded_at)->toJSON(),
        ];
    }
}
