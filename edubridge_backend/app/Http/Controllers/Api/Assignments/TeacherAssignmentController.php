<?php

namespace App\Http\Controllers\Api\Assignments;

use App\Actions\Assignments\AssignmentManager;
use App\Http\Requests\Assignments\StoreAssignmentRequest;
use App\Http\Requests\Assignments\UpdateAssignmentRequest;
use App\Http\Resources\Assignments\AssignmentResource;
use App\Models\Assignment;
use App\Models\Teacher;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TeacherAssignmentController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Assignment::class);
        $teacher = $this->currentTeacher();

        return ApiResponse::data(AssignmentResource::collection(
            Assignment::query()
                ->with('attachments')
                ->where('assigned_by_teacher_id', $teacher->id)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
        )->resolve());
    }

    public function store(StoreAssignmentRequest $request, AssignmentManager $manager): JsonResponse
    {
        Gate::authorize('create', Assignment::class);

        return ApiResponse::data(
            (new AssignmentResource($manager->create($this->currentTeacher(), $request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateAssignmentRequest $request, int $assignment, AssignmentManager $manager): JsonResponse
    {
        $assignment = Assignment::query()->with('attachments')->findOrFail($assignment);
        Gate::authorize('update', $assignment);

        return ApiResponse::data(
            (new AssignmentResource($manager->update($assignment, $this->currentTeacher(), $request->validated())))->resolve($request),
        );
    }

    public function publish(int $assignment, AssignmentManager $manager): JsonResponse
    {
        $assignment = Assignment::query()->with('attachments')->findOrFail($assignment);
        Gate::authorize('publish', $assignment);

        return ApiResponse::data((new AssignmentResource($manager->publish($assignment)))->resolve());
    }

    public function destroy(int $assignment, AssignmentManager $manager): JsonResponse
    {
        $assignment = Assignment::query()->with('attachments')->findOrFail($assignment);
        Gate::authorize('delete', $assignment);

        return ApiResponse::data((new AssignmentResource($manager->archive($assignment)))->resolve());
    }

    private function currentTeacher(): Teacher
    {
        $user = request()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $teacher = Teacher::query()
            ->where('central_user_id', $user->id)
            ->where('status', Teacher::STATUS_ACTIVE)
            ->first();

        return $teacher ?? throw new NotFoundHttpException;
    }
}
