<?php

namespace App\Http\Controllers\Api\People;

use App\Actions\People\PeopleProfileManager;
use App\Http\Requests\People\StoreTeacherRequest;
use App\Http\Requests\People\UpdateTeacherRequest;
use App\Http\Resources\People\TeacherResource;
use App\Models\Teacher;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TeacherController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Teacher::class);

        return ApiResponse::data(TeacherResource::collection(
            Teacher::query()->orderBy('full_name')->get(),
        )->resolve());
    }

    public function store(StoreTeacherRequest $request, PeopleProfileManager $manager): JsonResponse
    {
        Gate::authorize('create', Teacher::class);

        return ApiResponse::data(
            (new TeacherResource($manager->createTeacher($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function show(int $teacher): JsonResponse
    {
        $teacher = Teacher::query()->findOrFail($teacher);
        Gate::authorize('view', $teacher);

        return ApiResponse::data((new TeacherResource($teacher))->resolve());
    }

    public function update(UpdateTeacherRequest $request, int $teacher, PeopleProfileManager $manager): JsonResponse
    {
        $teacher = Teacher::query()->findOrFail($teacher);
        Gate::authorize('update', $teacher);

        return ApiResponse::data(
            (new TeacherResource($manager->updateTeacher($teacher, $request->validated())))->resolve($request),
        );
    }

    public function destroy(int $teacher, PeopleProfileManager $manager): JsonResponse
    {
        $teacher = Teacher::query()->findOrFail($teacher);
        Gate::authorize('delete', $teacher);

        return ApiResponse::data((new TeacherResource($manager->archiveTeacher($teacher)))->resolve());
    }
}
