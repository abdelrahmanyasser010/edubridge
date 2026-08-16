<?php

namespace App\Actions\Mobile;

use App\Models\Assessment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Support\CurrentTeacher;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TeacherMobileManager
{
    public function __construct(private readonly CurrentTeacher $teachers) {}

    /** @return array<string, mixed> */
    public function summary(int $centralUserId): array
    {
        $teacher = $this->teachers->resolve($centralUserId);
        $allocationIds = $this->allocationIds($teacher);

        $todaySessions = DB::connection('tenant')->table('teaching_sessions')
            ->whereIn('allocation_id', $allocationIds)
            ->whereDate('session_date', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->count();

        $pendingAttendance = DB::connection('tenant')->table('teaching_sessions')
            ->whereIn('allocation_id', $allocationIds)
            ->whereDate('session_date', '<=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')->from('attendance_records')->whereColumn('attendance_records.teaching_session_id', 'teaching_sessions.id');
            })
            ->count();

        $draftAssignments = DB::connection('tenant')->table('assignments')
            ->where('assigned_by_teacher_id', $teacher->id)
            ->where('status', 'draft')
            ->count();

        $pendingGrading = DB::connection('tenant')->table('assessments')
            ->whereIn('allocation_id', $allocationIds)
            ->whereIn('status', ['draft', 'pending_approval'])
            ->count();

        $substitutions = DB::connection('tenant')->table('teacher_substitutions')
            ->where('substitute_teacher_id', $teacher->id)
            ->where('status', 'pending')
            ->count();

        $unread = DB::connection('tenant')->table('notification_deliveries')
            ->where('central_user_id', $centralUserId)
            ->whereNull('read_at')
            ->count();

        return [
            'teacher' => ['id' => (string) $teacher->id, 'full_name' => $teacher->full_name, 'employee_number' => $teacher->employee_number],
            'today_classes_count' => $todaySessions,
            'pending_attendance_count' => $pendingAttendance,
            'draft_assignments_count' => $draftAssignments,
            'pending_grading_count' => $pendingGrading,
            'pending_substitutions_count' => $substitutions,
            'unread_notifications_count' => $unread,
        ];
    }

    /** @return LengthAwarePaginator<int, object> */
    public function classes(int $centralUserId, array $filters): LengthAwarePaginator
    {
        $teacher = $this->teachers->resolve($centralUserId);

        return DB::connection('tenant')->table('teacher_section_subject as tss')
            ->join('sections', 'sections.id', '=', 'tss.section_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->join('academic_terms', 'academic_terms.id', '=', 'tss.academic_term_id')
            ->where('tss.teacher_id', $teacher->id)
            ->where('tss.status', TeacherSectionSubject::STATUS_ACTIVE)
            ->when($filters['academic_term_id'] ?? null, fn ($query, mixed $termId) => $query->where('tss.academic_term_id', $termId))
            ->select([
                'tss.id as allocation_id', 'tss.is_homeroom', 'tss.weekly_quota',
                'sections.id as section_id', 'sections.name as section_name', 'sections.code as section_code',
                'grade_levels.id as grade_level_id', 'grade_levels.name as grade_level_name',
                'subjects.id as subject_id', 'subjects.name as subject_name', 'subjects.code as subject_code',
                'academic_terms.id as academic_term_id', 'academic_terms.name as academic_term_name',
            ])
            ->selectSub(function ($query) {
                $query->from('students')->selectRaw('COUNT(*)')->whereColumn('students.section_id', 'sections.id')->where('students.status', Student::STATUS_ACTIVE);
            }, 'students_count')
            ->orderBy('grade_levels.sort_order')
            ->orderBy('sections.name')
            ->orderBy('subjects.name')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @return array<string, mixed> */
    public function classDetail(int $centralUserId, int $sectionId): array
    {
        $teacher = $this->teachers->resolve($centralUserId);
        $allocations = DB::connection('tenant')->table('teacher_section_subject as tss')
            ->join('sections', 'sections.id', '=', 'tss.section_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->join('academic_terms', 'academic_terms.id', '=', 'tss.academic_term_id')
            ->where('tss.teacher_id', $teacher->id)
            ->where('tss.section_id', $sectionId)
            ->where('tss.status', TeacherSectionSubject::STATUS_ACTIVE)
            ->get([
                'tss.id as allocation_id', 'tss.is_homeroom', 'tss.weekly_quota',
                'sections.id as section_id', 'sections.name as section_name', 'sections.code as section_code',
                'grade_levels.id as grade_level_id', 'grade_levels.name as grade_level_name',
                'subjects.id as subject_id', 'subjects.name as subject_name',
                'academic_terms.id as academic_term_id', 'academic_terms.name as academic_term_name',
            ]);

        if ($allocations->isEmpty()) {
            throw new NotFoundHttpException;
        }

        $first = $allocations->first();

        return [
            'section' => [
                'id' => (string) $first->section_id,
                'name' => $first->section_name,
                'code' => $first->section_code,
                'grade_level' => ['id' => (string) $first->grade_level_id, 'name' => $first->grade_level_name],
                'students_count' => Student::query()->where('section_id', $sectionId)->where('status', Student::STATUS_ACTIVE)->count(),
            ],
            'allocations' => $allocations->map(fn (object $row): array => [
                'id' => (string) $row->allocation_id,
                'subject' => ['id' => (string) $row->subject_id, 'name' => $row->subject_name],
                'academic_term' => ['id' => (string) $row->academic_term_id, 'name' => $row->academic_term_name],
                'weekly_quota' => (int) $row->weekly_quota,
                'is_homeroom' => (bool) $row->is_homeroom,
            ])->values()->all(),
        ];
    }

    /** @return LengthAwarePaginator<int, Student> */
    public function classStudents(int $centralUserId, int $sectionId, int $perPage = 50): LengthAwarePaginator
    {
        $teacher = $this->teachers->resolve($centralUserId);
        $owns = TeacherSectionSubject::query()
            ->where('teacher_id', $teacher->id)
            ->where('section_id', $sectionId)
            ->where('status', TeacherSectionSubject::STATUS_ACTIVE)
            ->exists();

        if (! $owns) {
            throw new NotFoundHttpException;
        }

        return Student::query()
            ->where('section_id', $sectionId)
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->paginate($perPage);
    }

    /** @return list<array<string, mixed>> */
    public function schedule(int $centralUserId, string $date): array
    {
        $teacher = $this->teachers->resolve($centralUserId);

        return DB::connection('tenant')->table('teaching_sessions')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'teaching_sessions.allocation_id')
            ->join('sections', 'sections.id', '=', 'tss.section_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->leftJoin('schedule_slots', 'schedule_slots.id', '=', 'teaching_sessions.schedule_slot_id')
            ->where('tss.teacher_id', $teacher->id)
            ->whereDate('teaching_sessions.session_date', $date)
            ->orderBy('teaching_sessions.starts_at')
            ->get([
                'teaching_sessions.id as session_id', 'teaching_sessions.session_date', 'teaching_sessions.starts_at', 'teaching_sessions.ends_at', 'teaching_sessions.status',
                'tss.id as allocation_id', 'sections.id as section_id', 'sections.name as section_name', 'grade_levels.name as grade_level_name',
                'subjects.id as subject_id', 'subjects.name as subject_name', 'schedule_slots.room',
            ])
            ->map(fn (object $row): array => [
                'session_id' => (string) $row->session_id,
                'allocation_id' => (string) $row->allocation_id,
                'date' => (string) $row->session_date,
                'starts_at' => (string) $row->starts_at,
                'ends_at' => (string) $row->ends_at,
                'status' => (string) $row->status,
                'room' => $row->room,
                'section' => ['id' => (string) $row->section_id, 'name' => $row->section_name, 'grade_level_name' => $row->grade_level_name],
                'subject' => ['id' => (string) $row->subject_id, 'name' => $row->subject_name],
            ])->values()->all();
    }

    /** @return LengthAwarePaginator<int, Assessment> */
    public function assessments(int $centralUserId, array $filters): LengthAwarePaginator
    {
        $teacher = $this->teachers->resolve($centralUserId);
        $allocationIds = $this->allocationIds($teacher);

        return Assessment::query()
            ->whereIn('allocation_id', $allocationIds)
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->when($filters['allocation_id'] ?? null, fn ($query, mixed $allocationId) => $query->where('allocation_id', $allocationId))
            ->when($filters['academic_term_id'] ?? null, fn ($query, mixed $termId) => $query->where('academic_term_id', $termId))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @return array<string, mixed> */
    public function gradebook(int $centralUserId, int $sectionId): array
    {
        $teacher = $this->teachers->resolve($centralUserId);
        $allocationIds = TeacherSectionSubject::query()
            ->where('teacher_id', $teacher->id)
            ->where('section_id', $sectionId)
            ->where('status', TeacherSectionSubject::STATUS_ACTIVE)
            ->pluck('id');

        if ($allocationIds->isEmpty()) {
            throw new NotFoundHttpException;
        }

        $assessments = DB::connection('tenant')->table('assessments')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'assessments.allocation_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->whereIn('assessments.allocation_id', $allocationIds->all())
            ->orderByDesc('assessments.id')
            ->get(['assessments.id', 'assessments.title', 'assessments.type', 'assessments.max_score', 'assessments.status', 'subjects.id as subject_id', 'subjects.name as subject_name']);

        $students = Student::query()->where('section_id', $sectionId)->where('status', Student::STATUS_ACTIVE)->orderBy('full_name')->get();
        $entries = DB::connection('tenant')->table('grade_entries')
            ->whereIn('assessment_id', $assessments->pluck('id')->all())
            ->get(['id', 'assessment_id', 'student_id', 'score', 'feedback', 'revision'])
            ->groupBy('student_id');

        return [
            'section_id' => (string) $sectionId,
            'assessments' => $assessments->map(fn (object $assessment): array => [
                'id' => (string) $assessment->id,
                'title' => $assessment->title,
                'type' => $assessment->type,
                'max_score' => $assessment->max_score,
                'status' => $assessment->status,
                'subject' => ['id' => (string) $assessment->subject_id, 'name' => $assessment->subject_name],
            ])->values()->all(),
            'students' => $students->map(function (Student $student) use ($entries): array {
                $rows = $entries->get($student->id, collect());

                return [
                    'id' => (string) $student->id,
                    'full_name' => $student->full_name,
                    'admission_number' => $student->admission_number,
                    'grades' => $rows->map(fn (object $entry): array => [
                        'grade_entry_id' => (string) $entry->id,
                        'assessment_id' => (string) $entry->assessment_id,
                        'score' => $entry->score,
                        'feedback' => $entry->feedback,
                        'revision' => (int) $entry->revision,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /** @return list<int> */
    private function allocationIds(Teacher $teacher): array
    {
        return TeacherSectionSubject::query()
            ->where('teacher_id', $teacher->id)
            ->where('status', TeacherSectionSubject::STATUS_ACTIVE)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
