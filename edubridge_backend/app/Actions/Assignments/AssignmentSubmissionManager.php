<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\FileObject;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AssignmentSubmissionManager
{
    public function submit(Assignment $assignment, Student $student, FileObject $file, User $submitter): AssignmentSubmission
    {
        return DB::connection('tenant')->transaction(function () use ($assignment, $student, $file, $submitter): AssignmentSubmission {
            $assignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->ensureDeadlineOpen($assignment);
            $this->ensureUsableSubmissionFile($file, $submitter);

            $submission = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($submission === null) {
                return AssignmentSubmission::query()->create([
                    'assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                    'submitted_by_central_user_id' => $submitter->id,
                    'file_id' => $file->id,
                    'status' => AssignmentSubmission::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'version' => 1,
                ])->refresh();
            }

            $submission->forceFill([
                'submitted_by_central_user_id' => $submitter->id,
                'file_id' => $file->id,
                'submitted_at' => now(),
                'version' => $submission->version + 1,
            ])->save();

            return $submission->refresh();
        });
    }

    private function ensureDeadlineOpen(Assignment $assignment): void
    {
        if ($assignment->status !== Assignment::STATUS_PUBLISHED) {
            throw new ConflictHttpException('Only published assignments can receive submissions.');
        }

        if ($assignment->due_at !== null && now()->greaterThan($assignment->due_at)) {
            throw new ConflictHttpException('Assignment deadline has passed.');
        }
    }

    private function ensureUsableSubmissionFile(FileObject $file, User $submitter): void
    {
        if ($file->owner_central_user_id !== $submitter->id || $file->scan_status !== FileObject::SCAN_CLEAN) {
            throw ValidationException::withMessages([
                'file_id' => ['The selected submission file must belong to the submitter and pass scanning first.'],
            ]);
        }
    }
}
