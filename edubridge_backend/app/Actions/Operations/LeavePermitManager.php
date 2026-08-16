<?php

namespace App\Actions\Operations;

use App\Models\Guardian;
use App\Models\LeavePermit;
use App\Models\Student;
use App\Models\StudentParent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeavePermitManager
{
    /** @param array{requested_leave_at:string,reason?:string|null} $data */
    public function create(Student $student, int $parentCentralUserId, array $data): LeavePermit
    {
        $parent = Guardian::query()
            ->where('central_user_id', $parentCentralUserId)
            ->where('status', Guardian::STATUS_ACTIVE)
            ->first();

        if ($parent === null || ! $this->ownsStudent($parent->id, $student->id)) {
            throw new NotFoundHttpException;
        }

        return LeavePermit::query()->create([
            'student_id' => $student->id,
            'parent_id' => $parent->id,
            'requested_leave_at' => $data['requested_leave_at'],
            'reason' => $data['reason'] ?? null,
            'status' => LeavePermit::STATUS_PENDING,
        ]);
    }

    /** @return array{permit: LeavePermit, token: string} */
    public function approve(LeavePermit $permit, int $reviewerCentralUserId, ?string $note): array
    {
        return DB::connection('tenant')->transaction(function () use ($permit, $reviewerCentralUserId, $note): array {
            $permit = LeavePermit::query()->lockForUpdate()->findOrFail($permit->id);
            $this->ensurePending($permit);
            $token = Str::random(64);

            $permit->forceFill([
                'status' => LeavePermit::STATUS_APPROVED,
                'reviewed_by_central_user_id' => $reviewerCentralUserId,
                'reviewed_at' => now(),
                'review_note' => $note,
                'gate_token_hash' => hash('sha256', $token),
                'gate_token_expires_at' => now()->addHours(6),
            ])->save();

            return ['permit' => $permit->refresh(), 'token' => $token];
        });
    }

    public function reject(LeavePermit $permit, int $reviewerCentralUserId, ?string $note): LeavePermit
    {
        return DB::connection('tenant')->transaction(function () use ($permit, $reviewerCentralUserId, $note): LeavePermit {
            $permit = LeavePermit::query()->lockForUpdate()->findOrFail($permit->id);
            $this->ensurePending($permit);
            $permit->forceFill([
                'status' => LeavePermit::STATUS_REJECTED,
                'reviewed_by_central_user_id' => $reviewerCentralUserId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            return $permit->refresh();
        });
    }

    public function useToken(string $token): LeavePermit
    {
        return DB::connection('tenant')->transaction(function () use ($token): LeavePermit {
            $permit = LeavePermit::query()
                ->where('gate_token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($permit === null) {
                throw new NotFoundHttpException;
            }

            if ($permit->status !== LeavePermit::STATUS_APPROVED || $permit->gate_token_expires_at === null || now()->greaterThan($permit->gate_token_expires_at)) {
                if ($permit->status === LeavePermit::STATUS_APPROVED) {
                    $permit->forceFill(['status' => LeavePermit::STATUS_EXPIRED])->save();
                }

                throw new ConflictHttpException('Leave permit token is not usable.');
            }

            $permit->forceFill([
                'status' => LeavePermit::STATUS_USED,
                'used_at' => now(),
            ])->save();

            return $permit->refresh();
        });
    }

    private function ownsStudent(int $parentId, int $studentId): bool
    {
        return StudentParent::query()
            ->where('parent_id', $parentId)
            ->where('student_id', $studentId)
            ->where('status', StudentParent::STATUS_ACTIVE)
            ->exists();
    }

    private function ensurePending(LeavePermit $permit): void
    {
        if ($permit->status !== LeavePermit::STATUS_PENDING) {
            throw new ConflictHttpException('Leave permit has already been reviewed.');
        }
    }
}
