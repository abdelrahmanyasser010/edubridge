<?php

namespace App\Http\Resources\People;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use LogicException;

class StudentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Student) {
            throw new LogicException('StudentResource expects a Student model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'central_user_id' => $this->resource->central_user_id === null ? null : (string) $this->resource->central_user_id,
            'admission_number' => $this->resource->admission_number,
            'full_name' => $this->resource->full_name,
            'date_of_birth' => $this->resource->birthDateString(),
            'gender' => $this->resource->gender,
            'grade_level_id' => (string) $this->resource->grade_level_id,
            'section_id' => $this->resource->section_id === null ? null : (string) $this->resource->section_id,
            'residential_area_id' => $this->resource->residential_area_id === null ? null : (string) $this->resource->residential_area_id,
            'status' => $this->resource->status,
            'parents' => $this->parents((int) $this->resource->id),
        ];
    }

    /**
     * @return list<array{id: string, full_name: string, relationship: string}>
     */
    private function parents(int $studentId): array
    {
        return DB::connection('tenant')
            ->table('student_parent')
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('student_parent.student_id', $studentId)
            ->where('student_parent.status', 'active')
            ->select(['parents.id', 'parents.full_name', 'student_parent.relationship'])
            ->orderByDesc('student_parent.is_primary')
            ->orderBy('parents.full_name')
            ->get()
            ->map(fn (object $parent): array => [
                'id' => (string) $parent->id,
                'full_name' => (string) $parent->full_name,
                'relationship' => (string) $parent->relationship,
            ])
            ->values()
            ->all();
    }
}
