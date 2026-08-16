<?php

namespace App\Actions\Operations;

use App\Actions\Notifications\NotificationManager;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Models\TeacherSubstitution;
use App\Models\TeachingSession;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TeacherSubstitutionManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationManager $notifications,
    ) {}

    /** @param array{teaching_session_id:int,substitute_teacher_id:int,reason?:string|null} $data */
    public function create(array $data, int $actorCentralUserId): TeacherSubstitution
    {
        $session = TeachingSession::query()->findOrFail($data['teaching_session_id']);
        $allocation = TeacherSectionSubject::query()->findOrFail($session->allocation_id);
        $substitute = Teacher::query()->whereKey($data['substitute_teacher_id'])->where('status', Teacher::STATUS_ACTIVE)->firstOrFail();

        if ((int) $substitute->id === (int) $allocation->teacher_id) {
            throw new ConflictHttpException('Original teacher cannot be assigned as substitute.');
        }

        $this->ensureNoConflict($session, (int) $substitute->id);

        return DB::connection('tenant')->transaction(function () use ($data, $actorCentralUserId, $session, $allocation, $substitute): TeacherSubstitution {
            $substitution = TeacherSubstitution::query()->create([
                'teaching_session_id' => $session->id,
                'original_teacher_id' => $allocation->teacher_id,
                'substitute_teacher_id' => $substitute->id,
                'assigned_by_central_user_id' => $actorCentralUserId,
                'reason' => $data['reason'] ?? null,
                'status' => TeacherSubstitution::STATUS_PENDING,
            ]);

            $this->audit->record('teacher_substitution.created', TeacherSubstitution::class, (string) $substitution->id, null, [
                'teaching_session_id' => (string) $session->id,
                'substitute_teacher_id' => (string) $substitute->id,
                'status' => $substitution->status,
            ]);

            if ($substitute->central_user_id !== null) {
                $this->notifications->create(
                    'teacher_substitution.assigned',
                    'Substitution request',
                    $data['reason'] ?? 'You have been assigned as a substitute teacher.',
                    [(int) $substitute->central_user_id],
                    ['substitution_id' => (string) $substitution->id, 'teaching_session_id' => (string) $session->id],
                    $actorCentralUserId,
                );
            }

            return $substitution->refresh();
        });
    }

    /** @return list<array{id:string,full_name:string,specialization:?string}> */
    public function availableCandidates(TeachingSession $session): array
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($session->allocation_id);

        return Teacher::query()
            ->where('status', Teacher::STATUS_ACTIVE)
            ->where('id', '!=', $allocation->teacher_id)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'specialization'])
            ->filter(function (Teacher $teacher) use ($session): bool {
                try {
                    $this->ensureNoTeacherTimeConflict($session, (int) $teacher->id);

                    return true;
                } catch (ConflictHttpException) {
                    return false;
                }
            })
            ->map(fn (Teacher $teacher): array => [
                'id' => (string) $teacher->id,
                'full_name' => $teacher->full_name,
                'specialization' => $teacher->specialization,
            ])
            ->values()
            ->all();
    }

    public function accept(TeacherSubstitution $substitution, ?string $note): TeacherSubstitution
    {
        return $this->respond($substitution, TeacherSubstitution::STATUS_ACCEPTED, $note);
    }

    public function decline(TeacherSubstitution $substitution, ?string $note): TeacherSubstitution
    {
        return $this->respond($substitution, TeacherSubstitution::STATUS_DECLINED, $note);
    }

    private function respond(TeacherSubstitution $substitution, string $status, ?string $note): TeacherSubstitution
    {
        return DB::connection('tenant')->transaction(function () use ($substitution, $status, $note): TeacherSubstitution {
            $substitution = TeacherSubstitution::query()->lockForUpdate()->findOrFail($substitution->id);

            if ($substitution->status !== TeacherSubstitution::STATUS_PENDING) {
                throw new ConflictHttpException('Teacher substitution has already been answered.');
            }

            $before = $substitution->only(['status', 'response_note', 'responded_at']);
            $respondedAt = now();
            $substitution->forceFill([
                'status' => $status,
                'response_note' => $note,
                'responded_at' => $respondedAt,
            ])->save();

            $this->audit->record('teacher_substitution.'.$status, TeacherSubstitution::class, (string) $substitution->id, $before, [
                'status' => $substitution->status,
                'responded_at' => $respondedAt->toJSON(),
            ]);

            return $substitution->refresh();
        });
    }

    private function ensureNoConflict(TeachingSession $session, int $substituteTeacherId): void
    {
        $hasActiveSubstitutionForSession = TeacherSubstitution::query()
            ->where('teaching_session_id', $session->id)
            ->whereIn('status', [TeacherSubstitution::STATUS_PENDING, TeacherSubstitution::STATUS_ACCEPTED])
            ->exists();

        if ($hasActiveSubstitutionForSession) {
            throw new ConflictHttpException('Teaching session already has an active substitution.');
        }

        $this->ensureNoTeacherTimeConflict($session, $substituteTeacherId);
    }

    private function ensureNoTeacherTimeConflict(TeachingSession $session, int $substituteTeacherId): void
    {
        $hasTeachingConflict = TeachingSession::query()
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'teaching_sessions.allocation_id')
            ->where('teaching_sessions.id', '!=', $session->id)
            ->where('teaching_sessions.session_date', $session->sessionDateString())
            ->where('teaching_sessions.status', '!=', TeachingSession::STATUS_CANCELLED)
            ->where('tss.teacher_id', $substituteTeacherId)
            ->where('teaching_sessions.starts_at', '<', $session->ends_at)
            ->where('teaching_sessions.ends_at', '>', $session->starts_at)
            ->exists();

        $hasSubstitutionConflict = TeacherSubstitution::query()
            ->join('teaching_sessions', 'teaching_sessions.id', '=', 'teacher_substitutions.teaching_session_id')
            ->where('teacher_substitutions.teaching_session_id', '!=', $session->id)
            ->where('teacher_substitutions.substitute_teacher_id', $substituteTeacherId)
            ->whereIn('teacher_substitutions.status', [TeacherSubstitution::STATUS_PENDING, TeacherSubstitution::STATUS_ACCEPTED])
            ->where('teaching_sessions.session_date', $session->sessionDateString())
            ->where('teaching_sessions.starts_at', '<', $session->ends_at)
            ->where('teaching_sessions.ends_at', '>', $session->starts_at)
            ->exists();

        if ($hasTeachingConflict || $hasSubstitutionConflict) {
            throw new ConflictHttpException('Substitute teacher has a conflicting session.');
        }
    }
}
