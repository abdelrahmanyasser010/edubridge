<?php

namespace App\Actions\Assignments;

use App\Actions\Notifications\NotificationManager;
use App\Models\Assignment;
use App\Models\FileObject;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AssignmentManager
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly NotificationManager $notifications,
    ) {}

    /**
     * @param  array{allocation_id:int,title:string,body?:string|null,due_at?:string|null,attachment_file_ids?:list<int>}  $data
     */
    public function create(Teacher $teacher, array $data): Assignment
    {
        return DB::connection('tenant')->transaction(function () use ($teacher, $data): Assignment {
            $this->ensureTeacherOwnsAllocation($teacher, (int) $data['allocation_id']);
            $this->ensureUsableFiles($teacher, $data['attachment_file_ids'] ?? []);

            $assignment = Assignment::query()->create([
                'allocation_id' => $data['allocation_id'],
                'assigned_by_teacher_id' => $teacher->id,
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'status' => Assignment::STATUS_DRAFT,
                'version' => 1,
            ]);

            $this->replaceAttachments($assignment, $data['attachment_file_ids'] ?? []);

            return $assignment->load('attachments');
        });
    }

    /**
     * @param  array{title?:string,body?:string|null,due_at?:string|null,attachment_file_ids?:list<int>}  $data
     */
    public function update(Assignment $assignment, Teacher $teacher, array $data): Assignment
    {
        return DB::connection('tenant')->transaction(function () use ($assignment, $teacher, $data): Assignment {
            $assignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->ensureDraft($assignment);

            if (array_key_exists('attachment_file_ids', $data)) {
                $this->ensureUsableFiles($teacher, $data['attachment_file_ids']);
            }

            $assignment->fill([
                ...array_intersect_key($data, array_flip(['title', 'body', 'due_at'])),
                'version' => $assignment->version + 1,
            ])->save();

            if (array_key_exists('attachment_file_ids', $data)) {
                $this->replaceAttachments($assignment, $data['attachment_file_ids']);
            }

            return $assignment->refresh()->load('attachments');
        });
    }

    public function publish(Assignment $assignment): Assignment
    {
        return DB::connection('tenant')->transaction(function () use ($assignment): Assignment {
            $assignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->ensureDraft($assignment);

            $assignment->forceFill([
                'status' => Assignment::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => $assignment->version + 1,
            ])->save();

            $this->outbox->publishAfterCommit('assignment.published', [
                'assignment_id' => (string) $assignment->id,
                'allocation_id' => (string) $assignment->allocation_id,
                'teacher_id' => (string) $assignment->assigned_by_teacher_id,
            ]);

            $recipientIds = $this->assignmentRecipientCentralUserIds($assignment);

            if ($recipientIds !== []) {
                $this->notifications->create(
                    type: 'assignment.published',
                    title: 'New assignment published',
                    body: $assignment->title,
                    recipientCentralUserIds: $recipientIds,
                    data: [
                        'assignment_id' => (string) $assignment->id,
                        'allocation_id' => (string) $assignment->allocation_id,
                    ],
                );
            }

            return $assignment->refresh()->load('attachments');
        });
    }

    public function archive(Assignment $assignment): Assignment
    {
        $assignment->forceFill([
            'status' => Assignment::STATUS_ARCHIVED,
            'version' => $assignment->version + 1,
        ])->save();

        return $assignment->refresh()->load('attachments');
    }

    private function ensureTeacherOwnsAllocation(Teacher $teacher, int $allocationId): void
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($allocationId);

        if ($allocation->teacher_id !== $teacher->id || $allocation->status !== TeacherSectionSubject::STATUS_ACTIVE) {
            throw new ConflictHttpException('Assignment allocation must be an active allocation owned by the teacher.');
        }
    }

    /** @param list<int> $fileIds */
    private function ensureUsableFiles(Teacher $teacher, array $fileIds): void
    {
        if ($fileIds === []) {
            return;
        }

        $validCount = FileObject::query()
            ->whereIn('id', $fileIds)
            ->where('owner_central_user_id', $teacher->central_user_id)
            ->where('scan_status', FileObject::SCAN_CLEAN)
            ->count();

        if ($validCount !== count($fileIds)) {
            throw ValidationException::withMessages([
                'attachment_file_ids' => ['Every attachment must belong to the teacher and pass scanning first.'],
            ]);
        }
    }

    /** @param list<int> $fileIds */
    private function replaceAttachments(Assignment $assignment, array $fileIds): void
    {
        $assignment->attachments()->delete();

        foreach ($fileIds as $fileId) {
            $assignment->attachments()->create(['file_id' => $fileId]);
        }
    }

    private function ensureDraft(Assignment $assignment): void
    {
        if ($assignment->status !== Assignment::STATUS_DRAFT) {
            throw new ConflictHttpException('Only draft assignments can be changed.');
        }
    }

    /**
     * @return list<int>
     */
    private function assignmentRecipientCentralUserIds(Assignment $assignment): array
    {
        $sectionId = TeacherSectionSubject::query()->where('id', $assignment->allocation_id)->value('section_id');

        if ($sectionId === null) {
            return [];
        }

        $studentUserIds = DB::connection('tenant')->table('students')
            ->where('section_id', $sectionId)
            ->where('status', 'active')
            ->whereNotNull('central_user_id')
            ->pluck('central_user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $parentUserIds = DB::connection('tenant')->table('students')
            ->join('student_parent', 'student_parent.student_id', '=', 'students.id')
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('students.section_id', $sectionId)
            ->where('students.status', 'active')
            ->where('student_parent.status', 'active')
            ->where('parents.status', 'active')
            ->whereNotNull('parents.central_user_id')
            ->pluck('parents.central_user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return array_values(array_unique([...$studentUserIds, ...$parentUserIds]));
    }
}
