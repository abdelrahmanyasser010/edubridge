<?php

namespace App\Actions\Assessment;

use App\Models\GradeAppeal;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class DashboardGradeAppealReader
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function index(array $filters): LengthAwarePaginator
    {
        return GradeAppeal::query()
            ->join('grade_entries', 'grade_entries.id', '=', 'grade_appeals.grade_entry_id')
            ->join('assessments', 'assessments.id', '=', 'grade_entries.assessment_id')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'assessments.allocation_id')
            ->join('students', 'students.id', '=', 'grade_appeals.student_id')
            ->join('sections', 'sections.id', '=', 'tss.section_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->join('parents', 'parents.id', '=', 'grade_appeals.parent_id')
            ->when($filters['status'] ?? null, fn ($query, mixed $value) => $query->where('grade_appeals.status', $value))
            ->when($filters['assessment_id'] ?? null, fn ($query, mixed $value) => $query->where('assessments.id', $value))
            ->when($filters['section_id'] ?? null, fn ($query, mixed $value) => $query->where('sections.id', $value))
            ->when($filters['student_id'] ?? null, fn ($query, mixed $value) => $query->where('students.id', $value))
            ->orderByRaw("case when grade_appeals.status = 'open' then 0 else 1 end")
            ->orderByDesc('grade_appeals.created_at')
            ->select([
                'grade_appeals.*',
                'grade_entries.score as current_score',
                'grade_entries.feedback as current_feedback',
                'grade_entries.revision as grade_revision',
                'assessments.id as assessment_id',
                'assessments.title as assessment_title',
                'assessments.max_score',
                'assessments.published_at',
                'students.full_name as student_name',
                'students.admission_number',
                'sections.id as section_id',
                'sections.name as section_name',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'parents.full_name as parent_name',
            ])
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->through(fn (GradeAppeal $appeal): array => [
                'id' => (string) $appeal->id,
                'grade_entry_id' => (string) $appeal->grade_entry_id,
                'assessment_id' => (string) $appeal->getAttribute('assessment_id'),
                'assessment_title' => $appeal->getAttribute('assessment_title'),
                'subject' => [
                    'id' => (string) $appeal->getAttribute('subject_id'),
                    'name' => $appeal->getAttribute('subject_name'),
                ],
                'student' => [
                    'id' => (string) $appeal->student_id,
                    'full_name' => $appeal->getAttribute('student_name'),
                    'admission_number' => $appeal->getAttribute('admission_number'),
                ],
                'section' => [
                    'id' => (string) $appeal->getAttribute('section_id'),
                    'name' => $appeal->getAttribute('section_name'),
                ],
                'parent' => [
                    'id' => (string) $appeal->parent_id,
                    'full_name' => $appeal->getAttribute('parent_name'),
                ],
                'reason' => $appeal->reason,
                'status' => $appeal->status,
                'current_score' => $appeal->getAttribute('current_score'),
                'max_score' => $appeal->getAttribute('max_score'),
                'current_feedback' => $appeal->getAttribute('current_feedback'),
                'grade_revision' => (int) $appeal->getAttribute('grade_revision'),
                'review_note' => $appeal->review_note,
                'reviewed_at' => $appeal->reviewed_at === null ? null : Carbon::parse($appeal->reviewed_at)->toJSON(),
                'created_at' => $appeal->created_at === null ? null : Carbon::parse($appeal->created_at)->toJSON(),
            ]);
    }
}
