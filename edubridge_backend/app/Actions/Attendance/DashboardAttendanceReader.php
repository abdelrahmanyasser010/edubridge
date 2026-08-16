<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\TeachingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardAttendanceReader
{
    /** @param array<string,mixed> $filters */
    public function daily(array $filters): array
    {
        $date = Carbon::parse((string) ($filters['date'] ?? now()->toDateString()))->toDateString();
        $sessionRows = DB::connection('tenant')->table('teaching_sessions as ts')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'ts.allocation_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->whereDate('ts.session_date', $date)
            ->where('ts.status', '!=', TeachingSession::STATUS_CANCELLED)
            ->when($filters['section_id'] ?? null, fn ($q, $v) => $q->where('tss.section_id', $v))
            ->orderBy('ts.starts_at')
            ->get(['ts.id', 'ts.starts_at', 'ts.ends_at', 'tss.section_id', 'subjects.name as subject_name']);

        $sectionIds = $sessionRows->pluck('section_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        if (($filters['section_id'] ?? null) !== null && $sectionIds === []) {
            $sectionIds = [(int) $filters['section_id']];
        }

        $students = Student::query()->where('status', Student::STATUS_ACTIVE)
            ->when($sectionIds !== [], fn ($q) => $q->whereIn('section_id', $sectionIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($sq) use ($v) {
                $sq->where('full_name', 'like', '%'.$v.'%')->orWhere('admission_number', 'like', '%'.$v.'%');
            }))
            ->with('section:id,name')
            ->orderBy('full_name')->get();

        $sessionIds = $sessionRows->pluck('id')->map(fn ($v) => (int) $v)->all();
        $records = AttendanceRecord::query()->whereIn('teaching_session_id', $sessionIds)->get()->keyBy(fn (AttendanceRecord $r) => $r->teaching_session_id.':'.$r->student_id);
        $sessionsBySection = $sessionRows->groupBy(fn ($r) => (int) $r->section_id);

        $items = $students->map(function (Student $student) use ($sessionsBySection, $records): array {
            $sessions = $sessionsBySection->get((int) $student->section_id, collect());
            $periods = [];
            $absent = 0;
            $late = 0;
            $excused = 0;
            $present = 0;
            $recorded = 0;
            foreach ($sessions as $session) {
                $record = $records->get($session->id.':'.$student->id);
                $status = $record?->status ?? 'not_recorded';
                if ($record) {
                    $recorded++;
                }
                if ($status === AttendanceRecord::STATUS_ABSENT) {
                    $absent++;
                } elseif ($status === AttendanceRecord::STATUS_LATE) {
                    $late++;
                } elseif ($status === AttendanceRecord::STATUS_EXCUSED) {
                    $excused++;
                } elseif ($status === AttendanceRecord::STATUS_PRESENT) {
                    $present++;
                }
                $periods[] = [
                    'teaching_session_id' => (string) $session->id,
                    'starts_at' => substr((string) $session->starts_at, 0, 5),
                    'ends_at' => substr((string) $session->ends_at, 0, 5),
                    'subject_name' => $session->subject_name,
                    'status' => $status,
                ];
            }
            $expected = count($periods);
            $fullDay = $expected > 0 && $recorded === $expected && $absent === $expected;
            $summary = $fullDay ? 'full_day_absence' : ($absent > 0 ? 'has_absence' : ($excused > 0 ? 'excused' : ($late > 0 ? 'late' : ($expected > 0 && $recorded === $expected ? 'complete' : 'incomplete'))));

            return [
                'student' => ['id' => (string) $student->id, 'full_name' => $student->full_name, 'admission_number' => $student->admission_number],
                'section' => ['id' => (string) $student->section_id, 'name' => $student->section?->name],
                'summary_status' => $summary, 'expected_periods' => $expected, 'recorded_periods' => $recorded,
                'present_periods' => $present, 'absent_periods' => $absent, 'late_periods' => $late, 'excused_periods' => $excused,
                'periods' => $periods,
            ];
        })->filter(fn (array $item) => ! isset($filters['status']) || $filters['status'] === null || $item['summary_status'] === $filters['status'])->values();

        $sessionCount = $sessionRows->count();
        $recordedSessions = $sessionRows->filter(function ($session) use ($records, $students) {
            $studentIds = $students->where('section_id', (int) $session->section_id)->pluck('id');
            if ($studentIds->isEmpty()) {
                return false;
            }

            return $studentIds->every(fn ($studentId) => $records->has($session->id.':'.$studentId));
        })->count();

        return [
            'date' => $date,
            'summary' => [
                'scheduled_sessions' => $sessionCount,
                'fully_recorded_sessions' => $recordedSessions,
                'students_with_absence' => $items->whereIn('summary_status', ['has_absence', 'full_day_absence'])->count(),
                'students_with_late' => $items->where('summary_status', 'late')->count(),
                'students_complete' => $items->where('summary_status', 'complete')->count(),
                'students_incomplete' => $items->where('summary_status', 'incomplete')->count(),
            ],
            'students' => $items->all(),
        ];
    }

    /** @param array<string,mixed> $filters */
    public function atRisk(array $filters): array
    {
        $threshold = (int) (DB::connection('tenant')->table('school_settings')->where('key', 'attendance.absence_warning_threshold')->value('value') ?? 5);
        $termId = (int) $filters['academic_term_id'];
        $rows = DB::connection('tenant')->table('students')
            ->leftJoin('sections', 'sections.id', '=', 'students.section_id')
            ->when($filters['section_id'] ?? null, fn ($q, $v) => $q->where('students.section_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($sq) use ($v) {
                $sq->where('students.full_name', 'like', '%'.$v.'%')->orWhere('students.admission_number', 'like', '%'.$v.'%');
            }))
            ->where('students.status', Student::STATUS_ACTIVE)
            ->select(['students.id', 'students.full_name', 'students.admission_number', 'students.section_id', 'sections.name as section_name'])
            ->orderBy('students.full_name')->get();

        $items = [];
        foreach ($rows as $student) {
            $expected = (int) DB::connection('tenant')->table('teaching_sessions as ts')
                ->join('teacher_section_subject as tss', 'tss.id', '=', 'ts.allocation_id')
                ->where('tss.academic_term_id', $termId)->where('tss.section_id', $student->section_id)
                ->where('ts.status', '!=', TeachingSession::STATUS_CANCELLED)->whereDate('ts.session_date', '<=', now()->toDateString())->count();
            if ($expected === 0) {
                continue;
            }
            $absent = (int) DB::connection('tenant')->table('attendance_records as ar')
                ->join('teaching_sessions as ts', 'ts.id', '=', 'ar.teaching_session_id')
                ->join('teacher_section_subject as tss', 'tss.id', '=', 'ts.allocation_id')
                ->where('ar.student_id', $student->id)->where('ar.status', AttendanceRecord::STATUS_ABSENT)
                ->where('tss.academic_term_id', $termId)->count();
            if ($absent < $threshold) {
                continue;
            }
            $recorded = (int) DB::connection('tenant')->table('attendance_records as ar')
                ->join('teaching_sessions as ts', 'ts.id', '=', 'ar.teaching_session_id')
                ->join('teacher_section_subject as tss', 'tss.id', '=', 'ts.allocation_id')
                ->where('ar.student_id', $student->id)->where('tss.academic_term_id', $termId)->count();
            $attendanceRate = $recorded > 0 ? round((($recorded - $absent) / $recorded) * 100, 1) : null;
            $items[] = [
                'student' => ['id' => (string) $student->id, 'full_name' => $student->full_name, 'admission_number' => $student->admission_number],
                'section' => ['id' => (string) $student->section_id, 'name' => $student->section_name],
                'unexcused_absent_periods' => $absent, 'recorded_periods' => $recorded, 'expected_periods' => $expected,
                'attendance_percentage' => $attendanceRate, 'warning_threshold' => $threshold,
                'reason' => 'unexcused_absence_threshold_reached',
            ];
        }
        usort($items,fn ($a,$b) => $b['unexcused_absent_periods'] <=> $a['unexcused_absent_periods']);

        return ['policy' => ['absence_warning_threshold' => $threshold, 'calculation_unit' => 'teaching_periods'], 'students' => $items];
    }
}
