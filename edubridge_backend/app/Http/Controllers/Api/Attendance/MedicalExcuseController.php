<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Actions\Attendance\MedicalExcuseManager;
use App\Http\Requests\Attendance\ApproveMedicalExcuseRequest;
use App\Http\Requests\Attendance\RejectMedicalExcuseRequest;
use App\Http\Requests\Attendance\StoreMedicalExcuseRequest;
use App\Http\Resources\Attendance\MedicalExcuseResource;
use App\Models\FileObject;
use App\Models\Guardian;
use App\Models\MedicalExcuse;
use App\Models\Student;
use App\Models\StudentParent;
use App\Policies\MedicalExcusePolicy;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MedicalExcuseController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', MedicalExcuse::class);

        return ApiResponse::data(MedicalExcuseResource::collection(
            MedicalExcuse::query()
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
        )->resolve());
    }

    public function store(StoreMedicalExcuseRequest $request, int $student, MedicalExcuseManager $manager): JsonResponse
    {
        Gate::authorize('create', MedicalExcuse::class);

        $student = Student::query()->findOrFail($student);
        $parent = $this->currentParent();

        if (! $this->ownsStudent($parent, $student)) {
            throw new NotFoundHttpException;
        }

        $file = FileObject::query()->findOrFail($request->validated('file_id'));
        $this->ensureUsableFile($file);

        return ApiResponse::data(
            (new MedicalExcuseResource($manager->create($student, $parent, $file, $request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function approve(ApproveMedicalExcuseRequest $request, int $excuse, MedicalExcuseManager $manager, MedicalExcusePolicy $policy): JsonResponse
    {
        $excuse = MedicalExcuse::query()->findOrFail($excuse);
        $this->authorizeReview($policy, $excuse);

        $result = $manager->approve($excuse, $this->currentUserId(), $request->validated('review_note'));

        return ApiResponse::data(
            (new MedicalExcuseResource($result['excuse']))->resolve($request),
            meta: ['updated_attendance_records' => $result['updated_attendance_records']],
        );
    }

    public function reject(RejectMedicalExcuseRequest $request, int $excuse, MedicalExcuseManager $manager, MedicalExcusePolicy $policy): JsonResponse
    {
        $excuse = MedicalExcuse::query()->findOrFail($excuse);
        $this->authorizeReview($policy, $excuse);

        return ApiResponse::data(
            (new MedicalExcuseResource($manager->reject($excuse, $this->currentUserId(), $request->validated('review_note'))))->resolve($request),
        );
    }

    private function currentParent(): Guardian
    {
        $parent = Guardian::query()
            ->where('central_user_id', $this->currentUserId())
            ->where('status', Guardian::STATUS_ACTIVE)
            ->first();

        return $parent ?? throw new NotFoundHttpException;
    }

    private function currentUserId(): int
    {
        $user = request()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        return (int) $user->id;
    }

    private function ownsStudent(Guardian $parent, Student $student): bool
    {
        return StudentParent::query()
            ->where('student_id', $student->id)
            ->where('parent_id', $parent->id)
            ->where('status', StudentParent::STATUS_ACTIVE)
            ->whereDate('valid_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', now()->toDateString()))
            ->exists();
    }

    private function ensureUsableFile(FileObject $file): void
    {
        if ($file->owner_central_user_id !== $this->currentUserId() || $file->scan_status !== FileObject::SCAN_CLEAN) {
            throw ValidationException::withMessages([
                'file_id' => ['The selected file must belong to the authenticated parent and pass scanning first.'],
            ]);
        }
    }

    private function authorizeReview(MedicalExcusePolicy $policy, MedicalExcuse $excuse): void
    {
        $user = request()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! $policy->review($user, $excuse)) {
            throw new AuthorizationException;
        }
    }
}
