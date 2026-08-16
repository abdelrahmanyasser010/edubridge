<?php

namespace App\Http\Resources\Assessment;

use App\Models\GradeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class GradeEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof GradeEntry) {
            throw new LogicException('GradeEntryResource expects a GradeEntry model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'assessment_id' => (string) $this->resource->assessment_id,
            'student_id' => (string) $this->resource->student_id,
            'score' => $this->resource->score,
            'feedback' => $this->resource->feedback,
            'entered_by_teacher_id' => (string) $this->resource->entered_by_teacher_id,
            'revision' => $this->resource->revision,
        ];
    }
}
