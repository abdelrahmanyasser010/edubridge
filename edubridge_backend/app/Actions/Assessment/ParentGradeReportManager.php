<?php

namespace App\Actions\Assessment;

use App\Models\Student;
use App\Models\StudentParent;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ParentGradeReportManager
{
    public function __construct(private readonly Outbox $outbox) {}

    /** @return list<array<string, mixed>> */
    public function recentAssessments(Student $student, int $parentCentralUserId, int $limit = 10): array
    {
        $this->ensureOwnership($student, $parentCentralUserId);

        return DB::connection('tenant')->table('grade_entries')
            ->join('assessments', 'assessments.id', '=', 'grade_entries.assessment_id')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'assessments.allocation_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->where('grade_entries.student_id', $student->id)
            ->where('assessments.status', 'published')
            ->whereNotNull('assessments.published_at')
            ->orderByDesc('assessments.published_at')
            ->limit($limit)
            ->get([
                'assessments.id as assessment_id',
                'assessments.title',
                'assessments.type',
                'assessments.max_score',
                'assessments.published_at',
                'subjects.name as subject_name',
                'grade_entries.score',
                'grade_entries.feedback',
            ])
            ->map(fn (object $row): array => [
                'assessment_id' => (string) $row->assessment_id,
                'title' => $row->title,
                'type' => $row->type,
                'subject_name' => $row->subject_name,
                'score' => $row->score,
                'max_score' => $row->max_score,
                'feedback' => $row->feedback,
                'published_at' => $row->published_at,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function terms(Student $student, int $parentCentralUserId): array
    {
        $this->ensureOwnership($student, $parentCentralUserId);

        return DB::connection('tenant')->table('grade_entries')
            ->join('assessments', 'assessments.id', '=', 'grade_entries.assessment_id')
            ->join('academic_terms', 'academic_terms.id', '=', 'assessments.academic_term_id')
            ->join('academic_years', 'academic_years.id', '=', 'academic_terms.academic_year_id')
            ->where('grade_entries.student_id', $student->id)
            ->whereIn('assessments.status', ['published', 'locked'])
            ->select([
                'academic_terms.id', 'academic_terms.name', 'academic_terms.starts_on', 'academic_terms.ends_on',
                'academic_years.id as academic_year_id', 'academic_years.name as academic_year_name',
            ])
            ->distinct()
            ->orderByDesc('academic_terms.starts_on')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'starts_on' => (string) $row->starts_on,
                'ends_on' => (string) $row->ends_on,
                'academic_year' => ['id' => (string) $row->academic_year_id, 'name' => (string) $row->academic_year_name],
            ])->all();
    }

    /** @return array<string, mixed> */
    public function termReport(Student $student, int $parentCentralUserId, int $academicTermId): array
    {
        $this->ensureOwnership($student, $parentCentralUserId);

        $term = DB::connection('tenant')->table('academic_terms')
            ->join('academic_years', 'academic_years.id', '=', 'academic_terms.academic_year_id')
            ->where('academic_terms.id', $academicTermId)
            ->first([
                'academic_terms.id', 'academic_terms.name', 'academic_terms.starts_on', 'academic_terms.ends_on',
                'academic_years.id as academic_year_id', 'academic_years.name as academic_year_name',
            ]);

        if ($term === null) {
            throw new NotFoundHttpException;
        }

        $entries = DB::connection('tenant')->table('grade_entries')
            ->join('assessments', 'assessments.id', '=', 'grade_entries.assessment_id')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'assessments.allocation_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->leftJoin('teachers', 'teachers.id', '=', 'grade_entries.entered_by_teacher_id')
            ->where('grade_entries.student_id', $student->id)
            ->where('assessments.academic_term_id', $academicTermId)
            ->whereIn('assessments.status', ['published', 'locked'])
            ->whereNotNull('grade_entries.score')
            ->orderBy('subjects.name')
            ->orderBy('assessments.published_at')
            ->get([
                'grade_entries.id as grade_entry_id', 'grade_entries.score', 'grade_entries.feedback',
                'assessments.id as assessment_id', 'assessments.title', 'assessments.type', 'assessments.max_score', 'assessments.weight', 'assessments.published_at',
                'subjects.id as subject_id', 'subjects.name as subject_name', 'teachers.full_name as teacher_name',
            ]);

        $subjects = $entries->groupBy('subject_id')->map(function ($rows): array {
            $weightedEarned = 0.0;
            $weightedMax = 0.0;
            foreach ($rows as $row) {
                $weight = max(0.0001, (float) $row->weight);
                $weightedEarned += (float) $row->score * $weight;
                $weightedMax += (float) $row->max_score * $weight;
            }

            return [
                'subject_id' => (string) $rows->first()->subject_id,
                'subject_name' => (string) $rows->first()->subject_name,
                'teacher_name' => $rows->first()->teacher_name,
                'percentage' => $weightedMax > 0 ? round(($weightedEarned / $weightedMax) * 100, 2) : null,
                'assessments' => $rows->map(fn (object $row): array => [
                    'grade_entry_id' => (string) $row->grade_entry_id,
                    'assessment_id' => (string) $row->assessment_id,
                    'title' => (string) $row->title,
                    'type' => (string) $row->type,
                    'score' => $row->score,
                    'max_score' => $row->max_score,
                    'weight' => $row->weight,
                    'feedback' => $row->feedback,
                    'published_at' => $row->published_at,
                ])->values()->all(),
            ];
        })->values()->all();

        $percentages = collect($subjects)->pluck('percentage')->filter(fn ($value) => $value !== null);

        return [
            'student' => ['id' => (string) $student->id, 'full_name' => $student->full_name, 'admission_number' => $student->admission_number],
            'term' => [
                'id' => (string) $term->id,
                'name' => (string) $term->name,
                'starts_on' => (string) $term->starts_on,
                'ends_on' => (string) $term->ends_on,
                'academic_year' => ['id' => (string) $term->academic_year_id, 'name' => (string) $term->academic_year_name],
            ],
            'overall_percentage' => $percentages->isEmpty() ? null : round((float) $percentages->avg(), 2),
            'subjects' => $subjects,
        ];
    }

    public function requestCertificate(Student $student, int $parentCentralUserId, int $academicTermId): string
    {
        $this->ensureOwnership($student, $parentCentralUserId);

        return $this->outbox->publishAfterCommit('certificate.generate_requested', [
            'student_id' => (string) $student->id,
            'parent_central_user_id' => (string) $parentCentralUserId,
            'academic_term_id' => (string) $academicTermId,
        ]);
    }

    private function ensureOwnership(Student $student, int $parentCentralUserId): void
    {
        $owns = StudentParent::query()
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('student_parent.student_id', $student->id)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->where('parents.central_user_id', $parentCentralUserId)
            ->where('parents.status', 'active')
            ->exists();

        if (! $owns) {
            throw new NotFoundHttpException;
        }
    }
}
