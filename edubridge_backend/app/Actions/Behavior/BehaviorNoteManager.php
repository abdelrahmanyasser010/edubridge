<?php

namespace App\Actions\Behavior;

use App\Models\BehaviorNote;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BehaviorNoteManager
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{student_id:int,allocation_id:int,title:string,body:string,severity:string} $data */
    public function create(Teacher $teacher, array $data): BehaviorNote
    {
        return DB::connection('tenant')->transaction(function () use ($teacher, $data): BehaviorNote {
            $allocation = TeacherSectionSubject::query()->findOrFail($data['allocation_id']);
            $student = Student::query()->findOrFail($data['student_id']);

            if ($allocation->teacher_id !== $teacher->id || $student->section_id !== $allocation->section_id) {
                throw new ConflictHttpException('Behavior note must target a student in an owned allocation section.');
            }

            $note = BehaviorNote::query()->create([
                ...$data,
                'created_by_teacher_id' => $teacher->id,
                'status' => BehaviorNote::STATUS_PENDING_REVIEW,
                'version' => 1,
            ]);

            $this->recordTransition($note, null, BehaviorNote::STATUS_PENDING_REVIEW, 'created for review');

            return $note->load('timeline');
        });
    }

    public function publish(BehaviorNote $note, int $actorCentralUserId, ?string $reviewNote): BehaviorNote
    {
        return $this->transition($note, BehaviorNote::STATUS_PUBLISHED, $actorCentralUserId, $reviewNote, [
            'reviewed_by_central_user_id' => $actorCentralUserId,
            'reviewed_at' => now(),
            'published_at' => now(),
        ]);
    }

    public function reject(BehaviorNote $note, int $actorCentralUserId, ?string $reviewNote): BehaviorNote
    {
        return $this->transition($note, BehaviorNote::STATUS_REJECTED, $actorCentralUserId, $reviewNote, [
            'reviewed_by_central_user_id' => $actorCentralUserId,
            'reviewed_at' => now(),
        ]);
    }

    public function acknowledge(BehaviorNote $note, int $actorCentralUserId, ?string $ackNote): BehaviorNote
    {
        return $this->transition($note, BehaviorNote::STATUS_ACKNOWLEDGED, $actorCentralUserId, $ackNote, [
            'acknowledged_at' => now(),
        ]);
    }

    public function resolve(BehaviorNote $note, int $actorCentralUserId, ?string $resolveNote): BehaviorNote
    {
        return $this->transition($note, BehaviorNote::STATUS_RESOLVED, $actorCentralUserId, $resolveNote, [
            'resolved_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function transition(BehaviorNote $note, string $toStatus, int $actorCentralUserId, ?string $transitionNote, array $extra): BehaviorNote
    {
        return DB::connection('tenant')->transaction(function () use ($note, $toStatus, $actorCentralUserId, $transitionNote, $extra): BehaviorNote {
            $note = BehaviorNote::query()->lockForUpdate()->findOrFail($note->id);
            $from = $note->status;
            $note->forceFill([
                ...$extra,
                'status' => $toStatus,
                'version' => $note->version + 1,
            ])->save();

            $this->recordTransition($note, $from, $toStatus, $transitionNote, $actorCentralUserId);
            $this->auditLogger->record('behavior_note.'.$toStatus, BehaviorNote::class, (string) $note->id, before: ['status' => $from], after: ['status' => $toStatus]);

            return $note->refresh()->load('timeline');
        });
    }

    private function recordTransition(BehaviorNote $note, ?string $fromStatus, string $toStatus, ?string $transitionNote, ?int $actorCentralUserId = null): void
    {
        $note->timeline()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_central_user_id' => $actorCentralUserId,
            'note' => $transitionNote,
        ]);
    }
}
