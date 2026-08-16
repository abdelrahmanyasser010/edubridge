<?php

namespace App\Http\Controllers\Api\People;

use App\Actions\People\PeopleProfileManager;
use App\Http\Requests\People\StoreStudentRequest;
use App\Http\Requests\People\UpdateStudentRequest;
use App\Http\Resources\People\StudentResource;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StudentController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Student::class);

        return ApiResponse::data(StudentResource::collection(
            Student::query()->orderBy('full_name')->get(),
        )->resolve());
    }

    public function store(StoreStudentRequest $request, PeopleProfileManager $manager): JsonResponse
    {
        Gate::authorize('create', Student::class);

        return ApiResponse::data(
            (new StudentResource($manager->createStudent($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function show(int $student): JsonResponse
    {
        $student = Student::query()->findOrFail($student);
        Gate::authorize('view', $student);

        return ApiResponse::data((new StudentResource($student))->resolve());
    }

    public function update(UpdateStudentRequest $request, int $student, PeopleProfileManager $manager): JsonResponse
    {
        $student = Student::query()->findOrFail($student);
        Gate::authorize('update', $student);

        return ApiResponse::data(
            (new StudentResource($manager->updateStudent($student, $request->validated())))->resolve($request),
        );
    }

    public function destroy(int $student, PeopleProfileManager $manager): JsonResponse
    {
        $student = Student::query()->findOrFail($student);
        Gate::authorize('delete', $student);

        return ApiResponse::data((new StudentResource($manager->archiveStudent($student)))->resolve());
    }
}
