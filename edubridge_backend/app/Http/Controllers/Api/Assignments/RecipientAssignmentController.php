<?php

namespace App\Http\Controllers\Api\Assignments;

use App\Actions\Assignments\AssignmentSubmissionManager;
use App\Http\Requests\Assignments\SubmitAssignmentRequest;
use App\Http\Resources\Assignments\AssignmentResource;
use App\Http\Resources\Assignments\AssignmentSubmissionResource;
use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\AssignmentSubmission;
use App\Models\FileObject;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RecipientAssignmentController
{
    public function parentIndex(int $student): JsonResponse
    {
        Gate::authorize('assignment.view');
        $student = $this->parentStudent($student);

        return ApiResponse::data(AssignmentResource::collection($this->assignmentsForStudent($student))->resolve());
    }

    public function studentIndex(): JsonResponse
    {
        Gate::authorize('assignment.view');
        $student = $this->currentStudent();

        return ApiResponse::data(AssignmentResource::collection($this->assignmentsForStudent($student))->resolve());
    }

    public function parentSubmit(SubmitAssignmentRequest $request, int $student, int $assignment, AssignmentSubmissionManager $manager): JsonResponse
    {
        return $this->submit($request, $this->parentStudent($student), $assignment, $manager);
    }

    public function studentSubmit(SubmitAssignmentRequest $request, int $assignment, AssignmentSubmissionManager $manager): JsonResponse
    {
        return $this->submit($request, $this->currentStudent(), $assignment, $manager);
    }

    public function parentDownload(int $student, int $assignment, int $file): StreamedResponse
    {
        return $this->download($this->parentStudent($student), $assignment, $file);
    }

    public function studentDownload(int $assignment, int $file): StreamedResponse
    {
        return $this->download($this->currentStudent(), $assignment, $file);
    }

    private function submit(SubmitAssignmentRequest $request, Student $student, int $assignment, AssignmentSubmissionManager $manager): JsonResponse
    {
        $assignment = Assignment::query()->findOrFail($assignment);
        Gate::authorize('submitForStudent', [AssignmentSubmission::class, $assignment, $student]);

        $file = FileObject::query()->findOrFail($request->validated('file_id'));

        return ApiResponse::data(
            (new AssignmentSubmissionResource($manager->submit($assignment, $student, $file, $this->currentUser())))->resolve($request),
        );
    }

    private function download(Student $student, int $assignment, int $file): StreamedResponse
    {
        $assignment = Assignment::query()->findOrFail($assignment);
        Gate::authorize('viewForStudent', [AssignmentSubmission::class, $assignment, $student]);

        $attached = AssignmentAttachment::query()
            ->where('assignment_id', $assignment->id)
            ->where('file_id', $file)
            ->exists();

        if (! $attached) {
            throw new NotFoundHttpException;
        }

        $file = FileObject::query()->findOrFail($file);

        if ($file->scan_status !== FileObject::SCAN_CLEAN) {
            throw new ConflictHttpException('File is not available for download.');
        }

        if (! Storage::disk($file->disk)->exists($file->path)) {
            throw new NotFoundHttpException;
        }

        return response()->streamDownload(function () use ($file): void {
            echo Storage::disk($file->disk)->get($file->path);
        }, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return Collection<int, Assignment>
     */
    private function assignmentsForStudent(Student $student)
    {
        return Assignment::query()
            ->with('attachments')
            ->join('teacher_section_subject', 'teacher_section_subject.id', '=', 'assignments.allocation_id')
            ->where('teacher_section_subject.section_id', $student->section_id)
            ->where('assignments.status', Assignment::STATUS_PUBLISHED)
            ->orderByDesc('assignments.published_at')
            ->select('assignments.*')
            ->get();
    }

    private function parentStudent(int $student): Student
    {
        $student = Student::query()->findOrFail($student);

        $owned = StudentParent::query()
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('student_parent.student_id', $student->id)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->where('parents.central_user_id', $this->currentUser()->id)
            ->where('parents.status', 'active')
            ->whereDate('student_parent.valid_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('student_parent.valid_until')
                ->orWhereDate('student_parent.valid_until', '>=', now()->toDateString()))
            ->exists();

        if (! $owned) {
            throw new NotFoundHttpException;
        }

        return $student;
    }

    private function currentStudent(): Student
    {
        $student = Student::query()
            ->where('central_user_id', $this->currentUser()->id)
            ->where('status', Student::STATUS_ACTIVE)
            ->first();

        return $student ?? throw new NotFoundHttpException;
    }

    private function currentUser(): User
    {
        $user = request()->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
