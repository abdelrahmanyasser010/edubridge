<?php

namespace App\Actions\Attendance;

use App\Models\FileObject;
use App\Models\Guardian;
use App\Models\MedicalExcuse;
use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MedicalExcuseManager
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{starts_on:string,ends_on:string,reason?:string|null}  $data
     */
    public function create(Student $student, Guardian $parent, FileObject $file, array $data): MedicalExcuse
    {
        return MedicalExcuse::query()->create([
            'student_id' => $student->id,
            'parent_id' => $parent->id,
            'file_id' => $file->id,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'reason' => $data['reason'] ?? null,
            'status' => MedicalExcuse::STATUS_PENDING,
        ]);
    }

    /**
     * @return array{excuse: MedicalExcuse, updated_attendance_records: int}
     */
    public function approve(MedicalExcuse $excuse, int $reviewerCentralUserId, ?string $note): array
    {
        return DB::connection('tenant')->transaction(function () use ($excuse, $reviewerCentralUserId, $note): array {
            $excuse = MedicalExcuse::query()->lockForUpdate()->findOrFail($excuse->id);
            $this->ensurePending($excuse);

            $sessionIds = DB::connection('tenant')->table('teaching_sessions')
                ->whereDate('session_date', '>=', $excuse->startsOnString())
                ->whereDate('session_date', '<=', $excuse->endsOnString())
                ->pluck('id');

            $updated = DB::connection('tenant')->table('attendance_records')
                ->where('student_id', $excuse->student_id)
                ->where('status', 'absent')
                ->whereIn('teaching_session_id', $sessionIds)
                ->update([
                    'status' => 'excused',
                    'revision' => DB::raw('revision + 1'),
                    'updated_at' => now(),
                ]);

            $excuse->forceFill([
                'status' => MedicalExcuse::STATUS_APPROVED,
                'reviewed_by_central_user_id' => $reviewerCentralUserId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            $this->auditLogger->record(
                action: 'medical_excuse.approved',
                subjectType: MedicalExcuse::class,
                subjectId: (string) $excuse->id,
                after: [
                    'status' => MedicalExcuse::STATUS_APPROVED,
                    'updated_attendance_records' => $updated,
                ],
            );

            return [
                'excuse' => $excuse->refresh(),
                'updated_attendance_records' => $updated,
            ];
        });
    }

    public function reject(MedicalExcuse $excuse, int $reviewerCentralUserId, string $note): MedicalExcuse
    {
        return DB::connection('tenant')->transaction(function () use ($excuse, $reviewerCentralUserId, $note): MedicalExcuse {
            $excuse = MedicalExcuse::query()->lockForUpdate()->findOrFail($excuse->id);
            $this->ensurePending($excuse);

            $excuse->forceFill([
                'status' => MedicalExcuse::STATUS_REJECTED,
                'reviewed_by_central_user_id' => $reviewerCentralUserId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            $this->auditLogger->record(
                action: 'medical_excuse.rejected',
                subjectType: MedicalExcuse::class,
                subjectId: (string) $excuse->id,
                after: ['status' => MedicalExcuse::STATUS_REJECTED],
            );

            return $excuse->refresh();
        });
    }

    private function ensurePending(MedicalExcuse $excuse): void
    {
        if ($excuse->status !== MedicalExcuse::STATUS_PENDING) {
            throw new ConflictHttpException('Medical excuse has already been reviewed.');
        }
    }
}
