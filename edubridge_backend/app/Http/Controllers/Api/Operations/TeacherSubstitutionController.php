<?php

namespace App\Http\Controllers\Api\Operations;

use App\Actions\Operations\TeacherSubstitutionManager;
use App\Http\Requests\Operations\RespondTeacherSubstitutionRequest;
use App\Http\Requests\Operations\StoreTeacherSubstitutionRequest;
use App\Http\Resources\Operations\TeacherSubstitutionResource;
use App\Models\Teacher;
use App\Models\TeacherSubstitution;
use App\Models\TeachingSession;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TeacherSubstitutionController
{
    public function dashboardIndex(Request $request): JsonResponse
    {
        Gate::authorize('operations.view');

        $substitutions = TeacherSubstitution::query()
            ->latest()
            ->limit(250)
            ->get();

        return ApiResponse::data(TeacherSubstitutionResource::collection($substitutions)->resolve($request));
    }

    public function available(Request $request, int $session, TeacherSubstitutionManager $manager): JsonResponse
    {
        Gate::authorize('create', TeacherSubstitution::class);
        $teachingSession = TeachingSession::query()->findOrFail($session);

        return ApiResponse::data($manager->availableCandidates($teachingSession));
    }

    public function store(StoreTeacherSubstitutionRequest $request, TeacherSubstitutionManager $manager): JsonResponse
    {
        Gate::authorize('create', TeacherSubstitution::class);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data(
            (new TeacherSubstitutionResource($manager->create($request->validated(), (int) $user->id)))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function teacherIndex(Request $request): JsonResponse
    {
        Gate::authorize('viewForTeacher', TeacherSubstitution::class);
        $user = $request->user() ?? throw new AuthenticationException;
        $teacherId = Teacher::query()->where('central_user_id', $user->id)->value('id');

        return ApiResponse::data(TeacherSubstitutionResource::collection(
            TeacherSubstitution::query()
                ->where('substitute_teacher_id', $teacherId)
                ->latest()
                ->get()
        )->resolve($request));
    }

    public function accept(RespondTeacherSubstitutionRequest $request, int $substitution, TeacherSubstitutionManager $manager): JsonResponse
    {
        $substitution = TeacherSubstitution::query()->findOrFail($substitution);
        Gate::authorize('respond', $substitution);

        return ApiResponse::data((new TeacherSubstitutionResource($manager->accept($substitution, $request->validated('response_note'))))->resolve($request));
    }

    public function decline(RespondTeacherSubstitutionRequest $request, int $substitution, TeacherSubstitutionManager $manager): JsonResponse
    {
        $substitution = TeacherSubstitution::query()->findOrFail($substitution);
        Gate::authorize('respond', $substitution);

        return ApiResponse::data((new TeacherSubstitutionResource($manager->decline($substitution, $request->validated('response_note'))))->resolve($request));
    }
}
