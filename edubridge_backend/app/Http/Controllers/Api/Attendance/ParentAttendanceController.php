<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Requests\Attendance\ParentAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentParent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ParentAttendanceController
{
    public function __invoke(ParentAttendanceRequest $request, int $student): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        $studentModel = Student::query()->findOrFail($student);
        $parent = Guardian::query()
            ->where('central_user_id', $request->user()?->id)
            ->where('status', Guardian::STATUS_ACTIVE)
            ->first();

        if ($parent === null || ! $this->ownsStudent($parent, $studentModel)) {
            throw new NotFoundHttpException;
        }

        $query = DB::connection('tenant')->table('attendance_records')
            ->join('teaching_sessions', 'teaching_sessions.id', '=', 'attendance_records.teaching_session_id')
            ->leftJoin('teacher_section_subject', 'teacher_section_subject.id', '=', 'teaching_sessions.allocation_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'teacher_section_subject.subject_id')
            ->where('attendance_records.student_id', $studentModel->id)
            ->when($request->validated('from'), fn ($query, string $from) => $query->whereDate('teaching_sessions.session_date', '>=', $from))
            ->when($request->validated('to'), fn ($query, string $to) => $query->whereDate('teaching_sessions.session_date', '<=', $to));

        $records = (clone $query)
            ->orderByDesc('teaching_sessions.session_date')
            ->orderByDesc('teaching_sessions.starts_at')
            ->get([
                'attendance_records.id',
                'attendance_records.status',
                'attendance_records.revision',
                'attendance_records.submitted_at',
                'teaching_sessions.id as session_id',
                'teaching_sessions.session_date',
                'teaching_sessions.starts_at',
                'teaching_sessions.ends_at',
                'subjects.name as subject_name',
            ])
            ->map(fn (object $record): array => [
                'id' => (string) $record->id,
                'session_id' => (string) $record->session_id,
                'session_date' => (string) $record->session_date,
                'starts_at' => (string) $record->starts_at,
                'ends_at' => (string) $record->ends_at,
                'subject_name' => $record->subject_name,
                'status' => $record->status,
                'revision' => (int) $record->revision,
                'submitted_at' => (string) $record->submitted_at,
            ])
            ->values()
            ->all();

        return ApiResponse::data([
            'student' => [
                'id' => (string) $studentModel->id,
                'full_name' => $studentModel->full_name,
                'admission_number' => $studentModel->admission_number,
            ],
            'filters' => [
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
            ],
            'summary' => $this->summary($studentModel, $request->validated('from'), $request->validated('to')),
            'records' => $records,
        ]);
    }

    private function ownsStudent(Guardian $parent, Student $student): bool
    {
        return StudentParent::query()
            ->where('student_id', $student->id)
            ->where('parent_id', $parent->id)
            ->where('status', StudentParent::STATUS_ACTIVE)
            ->whereDate('valid_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', now()->toDateString()))
            ->exists();
    }

    /**
     * @return array{total:int,present:int,absent:int,late:int,excused:int}
     */
    private function summary(Student $student, ?string $from, ?string $to): array
    {
        $counts = DB::connection('tenant')->table('attendance_records')
            ->join('teaching_sessions', 'teaching_sessions.id', '=', 'attendance_records.teaching_session_id')
            ->where('attendance_records.student_id', $student->id)
            ->when($from, fn ($query, string $date) => $query->whereDate('teaching_sessions.session_date', '>=', $date))
            ->when($to, fn ($query, string $date) => $query->whereDate('teaching_sessions.session_date', '<=', $date))
            ->select('attendance_records.status', DB::raw('count(*) as aggregate'))
            ->groupBy('attendance_records.status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $counts->sum(),
            'present' => (int) ($counts[AttendanceRecord::STATUS_PRESENT] ?? 0),
            'absent' => (int) ($counts[AttendanceRecord::STATUS_ABSENT] ?? 0),
            'late' => (int) ($counts[AttendanceRecord::STATUS_LATE] ?? 0),
            'excused' => (int) ($counts[AttendanceRecord::STATUS_EXCUSED] ?? 0),
        ];
    }
}
