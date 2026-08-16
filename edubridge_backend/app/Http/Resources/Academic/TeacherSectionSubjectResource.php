<?php

namespace App\Http\Resources\Academic;

use App\Models\TeacherSectionSubject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class TeacherSectionSubjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof TeacherSectionSubject) {
            throw new LogicException('TeacherSectionSubjectResource expects a TeacherSectionSubject model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'academic_term_id' => (string) $this->resource->academic_term_id,
            'teacher_id' => (string) $this->resource->teacher_id,
            'section_id' => (string) $this->resource->section_id,
            'subject_id' => (string) $this->resource->subject_id,
            'weekly_quota' => $this->resource->weekly_quota,
            'is_homeroom' => $this->resource->is_homeroom,
            'status' => $this->resource->status,
        ];
    }
}
