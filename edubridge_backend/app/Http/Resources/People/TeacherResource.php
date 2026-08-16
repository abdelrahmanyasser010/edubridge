<?php

namespace App\Http\Resources\People;

use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use LogicException;

class TeacherResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Teacher) {
            throw new LogicException('TeacherResource expects a Teacher model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'central_user_id' => $this->resource->central_user_id === null ? null : (string) $this->resource->central_user_id,
            'employee_number' => $this->resource->employee_number,
            'full_name' => $this->resource->full_name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'specialization' => $this->resource->specialization,
            'assigned_sections' => $this->assignedSections((int) $this->resource->id),
            'assigned_subjects' => $this->assignedSubjects((int) $this->resource->id),
            'status' => $this->resource->status,
        ];
    }

    /**
     * @return list<array{id: string, name: string, code: string|null}>
     */
    private function assignedSections(int $teacherId): array
    {
        return DB::connection('tenant')
            ->table('teacher_section_subject')
            ->join('sections', 'sections.id', '=', 'teacher_section_subject.section_id')
            ->where('teacher_section_subject.teacher_id', $teacherId)
            ->where('teacher_section_subject.status', Teacher::STATUS_ACTIVE)
            ->select(['sections.id', 'sections.name', 'sections.code'])
            ->distinct()
            ->orderBy('sections.name')
            ->get()
            ->map(fn (object $section): array => [
                'id' => (string) $section->id,
                'name' => (string) $section->name,
                'code' => $section->code === null ? null : (string) $section->code,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, name: string, code: string|null}>
     */
    private function assignedSubjects(int $teacherId): array
    {
        return DB::connection('tenant')
            ->table('teacher_section_subject')
            ->join('subjects', 'subjects.id', '=', 'teacher_section_subject.subject_id')
            ->where('teacher_section_subject.teacher_id', $teacherId)
            ->where('teacher_section_subject.status', Teacher::STATUS_ACTIVE)
            ->select(['subjects.id', 'subjects.name', 'subjects.code'])
            ->distinct()
            ->orderBy('subjects.name')
            ->get()
            ->map(fn (object $subject): array => [
                'id' => (string) $subject->id,
                'name' => (string) $subject->name,
                'code' => $subject->code === null ? null : (string) $subject->code,
            ])
            ->values()
            ->all();
    }
}
