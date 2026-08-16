<?php

namespace App\Actions\Attendance;

use App\Actions\Notifications\NotificationManager;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Models\TeachingSession;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitAttendance
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationManager $notifications,
    ) {}

    /** @param list<array<string, mixed>> $records */
    public function handle(TeachingSession $session, Teacher $teacher, array $records): array
    {
        return DB::connection('tenant')->transaction(function () use ($session, $teacher, $records): array {
            $session = TeachingSession::query()->lockForUpdate()->findOrFail($session->id);

            if (AttendanceRecord::query()->where('teaching_session_id', $session->id)->exists()) {
                throw new ConflictHttpException('Attendance has already been submitted for this session.');
            }

            $this->ensureCompleteRoster($session, $records);
            $now = now();

            foreach ($records as $record) {
                AttendanceRecord::query()->create([
                    'teaching_session_id' => $session->id,
                    'student_id' => $record['student_id'],
                    'status' => $record['status'],
                    'recorded_by_teacher_id' => $teacher->id,
                    'submitted_at' => $now,
                    'revision' => 1,
                ]);
            }

            $session->forceFill(['status' => TeachingSession::STATUS_COMPLETED])->save();

            $payload = [
                'teaching_session_id' => (string) $session->id,
                'submitted_count' => count($records),
                'status' => 'submitted',
            ];

            $this->auditLogger->record(
                action: 'attendance.submitted',
                subjectType: 'teaching_session',
                subjectId: (string) $session->id,
                after: $payload,
            );

            $recipientIds = $this->attendanceRecipientCentralUserIds($records);

            if ($recipientIds !== []) {
                $this->notifications->create(
                    type: 'attendance.submitted',
                    title: 'Attendance update',
                    body: 'Attendance has been submitted for a class session.',
                    recipientCentralUserIds: $recipientIds,
                    data: [
                        'teaching_session_id' => (string) $session->id,
                    ],
                );
            }

            return $payload;
        });
    }

    /** @param list<array<string, mixed>> $records */
    private function ensureCompleteRoster(TeachingSession $session, array $records): void
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($session->allocation_id);
        $expectedIds = Student::query()
            ->where('section_id', $allocation->section_id)
            ->where('status', Student::STATUS_ACTIVE)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->sort()
            ->values()
            ->all();

        $submittedIds = collect($records)
            ->pluck('student_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($expectedIds !== $submittedIds) {
            throw ValidationException::withMessages([
                'records' => ['Attendance submission must include exactly every active student in the session section.'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<int>
     */
    private function attendanceRecipientCentralUserIds(array $records): array
    {
        $notifiableStudentIds = collect($records)
            ->filter(fn (array $record): bool => in_array($record['status'], [
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_EXCUSED,
            ], true))
            ->pluck('student_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($notifiableStudentIds === []) {
            return [];
        }

        return DB::connection('tenant')->table('student_parent')
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->whereIn('student_parent.student_id', $notifiableStudentIds)
            ->where('student_parent.status', 'active')
            ->where('parents.status', 'active')
            ->whereNotNull('parents.central_user_id')
            ->pluck('parents.central_user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
