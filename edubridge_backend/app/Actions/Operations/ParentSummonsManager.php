<?php

namespace App\Actions\Operations;

use App\Actions\Notifications\NotificationManager;
use App\Models\Guardian;
use App\Models\ParentSummons;
use App\Models\Student;
use App\Models\StudentParent;
use App\Support\AuditLogger;
use App\Support\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ParentSummonsManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationManager $notifications,
        private readonly Outbox $outbox,
    ) {}

    /** @param array{student_id:int,parent_id:int,scheduled_at:string,reason:string} $data */
    public function create(array $data, int $actorCentralUserId): ParentSummons
    {
        $student = Student::query()->findOrFail($data['student_id']);
        $parent = Guardian::query()
            ->whereKey($data['parent_id'])
            ->where('status', Guardian::STATUS_ACTIVE)
            ->firstOrFail();

        if (! $this->ownsStudent((int) $parent->id, (int) $student->id)) {
            throw new NotFoundHttpException;
        }

        return DB::connection('tenant')->transaction(function () use ($data, $actorCentralUserId, $parent, $student): ParentSummons {
            $summons = ParentSummons::query()->create([
                'student_id' => $student->id,
                'parent_id' => $parent->id,
                'created_by_central_user_id' => $actorCentralUserId,
                'scheduled_at' => $data['scheduled_at'],
                'reason' => $data['reason'],
                'status' => ParentSummons::STATUS_PENDING,
            ]);

            $this->audit->record('parent_summons.created', ParentSummons::class, (string) $summons->id, null, [
                'student_id' => (string) $student->id,
                'parent_id' => (string) $parent->id,
                'scheduled_at' => Carbon::parse($summons->scheduled_at)->toJSON(),
                'status' => $summons->status,
            ]);

            $this->notifications->create(
                'parent_summons.created',
                'Parent meeting requested',
                $data['reason'],
                [(int) $parent->central_user_id],
                ['summons_id' => (string) $summons->id, 'student_id' => (string) $student->id],
                $actorCentralUserId,
            );

            $this->outbox->publishAfterCommit(
                'parent_summons.reminder_due',
                ['summons_id' => (string) $summons->id, 'parent_central_user_id' => (string) $parent->central_user_id],
                Carbon::parse($summons->scheduled_at)->subHour(),
            );

            return $summons->refresh();
        });
    }

    /** @param array{response:string,response_note?:string|null} $data */
    public function respond(ParentSummons $summons, int $parentCentralUserId, array $data): ParentSummons
    {
        $parent = Guardian::query()
            ->where('central_user_id', $parentCentralUserId)
            ->where('status', Guardian::STATUS_ACTIVE)
            ->first();

        if ($parent === null || (int) $parent->id !== (int) $summons->parent_id || ! $this->ownsStudent((int) $parent->id, (int) $summons->student_id)) {
            throw new NotFoundHttpException;
        }

        return DB::connection('tenant')->transaction(function () use ($summons, $data): ParentSummons {
            $summons = ParentSummons::query()->lockForUpdate()->findOrFail($summons->id);

            if ($summons->status !== ParentSummons::STATUS_PENDING) {
                throw new ConflictHttpException('Parent summons has already been answered.');
            }

            $before = $summons->only(['status', 'response', 'response_note', 'responded_at']);

            $summons->forceFill([
                'status' => ParentSummons::STATUS_RESPONDED,
                'response' => $data['response'],
                'response_note' => $data['response_note'] ?? null,
                'responded_at' => now(),
            ])->save();

            $this->audit->record('parent_summons.responded', ParentSummons::class, (string) $summons->id, $before, [
                'status' => $summons->status,
                'response' => $summons->response,
                'responded_at' => Carbon::parse($summons->responded_at)->toJSON(),
            ]);

            return $summons->refresh();
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
}
