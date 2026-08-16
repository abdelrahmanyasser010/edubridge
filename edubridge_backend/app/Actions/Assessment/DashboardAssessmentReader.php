<?php

namespace App\Actions\Assessment;

use App\Models\Assessment;
use App\Models\GradeEntry;
use App\Models\Student;
use App\Models\TeacherSectionSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardAssessmentReader
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function assessments(array $filters): LengthAwarePaginator
    {
        $paginator = $this->baseQuery($filters)
            ->orderByDesc('assessments.created_at')
            ->paginate((int) ($filters['per_page'] ?? 25));

        $summaries = $this->gradeSummaries($paginator->items());
        $items = $this->items($paginator->items(), $summaries);

        return new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => $paginator->path(), 'pageName' => $paginator->getPageName()]
        );
    }

    /**
     * @param  array<int, Assessment>  $assessments
     * @param  array<int, array<string, int>>  $summaries
     * @return list<array<string, mixed>>
     */
    private function items(array $assessments, array $summaries): array
    {
        $items = [];

        foreach ($assessments as $assessment) {
            $items[] = $this->item($assessment, $summaries[(int) $assessment->id] ?? null);
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public function assessment(int $assessmentId): array
    {
        $assessment = $this->baseQuery([])
            ->where('assessments.id', $assessmentId)
            ->first();

        if (! $assessment instanceof Assessment) {
            throw new NotFoundHttpException;
        }

        $summary = $this->gradeSummaries([$assessment])[(int) $assessment->id] ?? null;

        return [
            ...$this->item($assessment, $summary),
            'entries' => $this->entries($assessment),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Assessment>
     */
    private function baseQuery(array $filters): Builder
    {
        return Assessment::query()
            ->select([
                'assessments.*',
                'teacher_section_subject.teacher_id',
                'teacher_section_subject.section_id',
                'teacher_section_subject.subject_id',
                'teachers.full_name as teacher_name',
                'sections.name as section_name',
                'sections.code as section_code',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
            ])
            ->join('teacher_section_subject', 'teacher_section_subject.id', '=', 'assessments.allocation_id')
            ->join('teachers', 'teachers.id', '=', 'teacher_section_subject.teacher_id')
            ->join('sections', 'sections.id', '=', 'teacher_section_subject.section_id')
            ->join('subjects', 'subjects.id', '=', 'teacher_section_subject.subject_id')
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $status) => $query->where('assessments.status', $status))
            ->when($filters['academic_term_id'] ?? null, fn (Builder $query, mixed $termId) => $query->where('assessments.academic_term_id', $termId))
            ->when($filters['teacher_id'] ?? null, fn (Builder $query, mixed $teacherId) => $query->where('teacher_section_subject.teacher_id', $teacherId))
            ->when($filters['section_id'] ?? null, fn (Builder $query, mixed $sectionId) => $query->where('teacher_section_subject.section_id', $sectionId))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, mixed $subjectId) => $query->where('teacher_section_subject.subject_id', $subjectId))
            ->when($filters['type'] ?? null, fn (Builder $query, mixed $type) => $query->where('assessments.type', $type))
            ->when($filters['from'] ?? null, fn (Builder $query, mixed $from) => $query->where('assessments.created_at', '>=', Carbon::parse((string) $from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, mixed $to) => $query->where('assessments.created_at', '<=', Carbon::parse((string) $to)->endOfDay()));
    }

    /**
     * @param  array<int, Assessment>  $assessments
     * @return array<int, array<string, int>>
     */
    private function gradeSummaries(array $assessments): array
    {
        $assessmentIds = array_map(fn (Assessment $assessment): int => (int) $assessment->id, $assessments);
        if ($assessmentIds === []) {
            return [];
        }

        /** @var Collection<int, object{assessment_id:int, entered_entries:int, scored_entries:int}> $gradeCounts */
        $gradeCounts = GradeEntry::query()
            ->selectRaw('assessment_id, COUNT(*) as entered_entries, SUM(CASE WHEN score IS NOT NULL THEN 1 ELSE 0 END) as scored_entries')
            ->whereIn('assessment_id', $assessmentIds)
            ->groupBy('assessment_id')
            ->get();

        $gradeCountsByAssessment = $gradeCounts->keyBy('assessment_id');
        $sectionIds = array_values(array_unique(array_map(fn (Assessment $assessment): int => (int) $assessment->getAttribute('section_id'), $assessments)));

        /** @var Collection<int, object{section_id:int, expected_students:int}> $studentCounts */
        $studentCounts = Student::query()
            ->selectRaw('section_id, COUNT(*) as expected_students')
            ->whereIn('section_id', $sectionIds)
            ->where('status', Student::STATUS_ACTIVE)
            ->groupBy('section_id')
            ->get();

        $studentCountsBySection = $studentCounts->keyBy('section_id');
        $summaries = [];

        foreach ($assessments as $assessment) {
            $counts = $gradeCountsByAssessment->get((int) $assessment->id);
            $expectedStudents = (int) ($studentCountsBySection->get((int) $assessment->getAttribute('section_id'))->expected_students ?? 0);
            $enteredEntries = (int) ($counts->entered_entries ?? 0);
            $scoredEntries = (int) ($counts->scored_entries ?? 0);
            $summaries[(int) $assessment->id] = [
                'expected_students' => $expectedStudents,
                'entered_entries' => $enteredEntries,
                'scored_entries' => $scoredEntries,
                'missing_scores' => max($expectedStudents - $scoredEntries, 0),
            ];
        }

        return $summaries;
    }

    /** @param array<string, int>|null $summary */
    private function item(Assessment $assessment, ?array $summary): array
    {
        return [
            'id' => (string) $assessment->id,
            'academic_term_id' => (string) $assessment->academic_term_id,
            'allocation_id' => (string) $assessment->allocation_id,
            'title' => $assessment->title,
            'type' => $assessment->type,
            'max_score' => $assessment->max_score,
            'weight' => $assessment->weight,
            'status' => $assessment->status,
            'teacher' => [
                'id' => (string) $assessment->getAttribute('teacher_id'),
                'full_name' => $assessment->getAttribute('teacher_name'),
            ],
            'section' => [
                'id' => (string) $assessment->getAttribute('section_id'),
                'name' => $assessment->getAttribute('section_name'),
                'code' => $assessment->getAttribute('section_code'),
            ],
            'subject' => [
                'id' => (string) $assessment->getAttribute('subject_id'),
                'name' => $assessment->getAttribute('subject_name'),
                'code' => $assessment->getAttribute('subject_code'),
            ],
            'grade_summary' => $summary ?? [
                'expected_students' => 0,
                'entered_entries' => 0,
                'scored_entries' => 0,
                'missing_scores' => 0,
            ],
            'available_actions' => $this->availableActions($assessment),
            'submitted_at' => $assessment->submitted_at === null ? null : Carbon::parse($assessment->submitted_at)->toJSON(),
            'approved_by_central_user_id' => $assessment->approved_by_central_user_id === null ? null : (string) $assessment->approved_by_central_user_id,
            'approved_at' => $assessment->approved_at === null ? null : Carbon::parse($assessment->approved_at)->toJSON(),
            'published_at' => $assessment->published_at === null ? null : Carbon::parse($assessment->published_at)->toJSON(),
            'locked_at' => $assessment->locked_at === null ? null : Carbon::parse($assessment->locked_at)->toJSON(),
        ];
    }

    /** @return list<string> */
    private function availableActions(Assessment $assessment): array
    {
        if ($assessment->status === Assessment::STATUS_PENDING_APPROVAL) {
            return ['approve'];
        }

        if ($assessment->status === Assessment::STATUS_APPROVED && $assessment->approved_at !== null && $assessment->published_at === null) {
            return ['publish'];
        }

        if ($assessment->status === Assessment::STATUS_PUBLISHED && $assessment->published_at !== null) {
            return ['lock'];
        }

        return [];
    }

    /** @return list<array<string, mixed>> */
    private function entries(Assessment $assessment): array
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($assessment->allocation_id);

        return Student::query()
            ->select([
                'students.id',
                'students.full_name',
                'students.admission_number',
                'grade_entries.id as entry_id',
                'grade_entries.score',
                'grade_entries.feedback',
                'grade_entries.revision',
            ])
            ->leftJoin('grade_entries', function ($join) use ($assessment) {
                $join->on('grade_entries.student_id', '=', 'students.id')
                    ->where('grade_entries.assessment_id', '=', $assessment->id);
            })
            ->where('students.section_id', $allocation->section_id)
            ->where('students.status', Student::STATUS_ACTIVE)
            ->orderBy('students.full_name')
            ->get()
            ->map(fn (Student $student): array => [
                'student' => [
                    'id' => (string) $student->id,
                    'full_name' => $student->full_name,
                    'admission_number' => $student->admission_number,
                ],
                'entry' => $student->getAttribute('entry_id') === null ? null : [
                    'id' => (string) $student->getAttribute('entry_id'),
                    'score' => $student->getAttribute('score'),
                    'feedback' => $student->getAttribute('feedback'),
                    'revision' => (int) $student->getAttribute('revision'),
                ],
            ])
            ->all();
    }
}
