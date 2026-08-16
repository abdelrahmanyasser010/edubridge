<?php

namespace App\Http\Controllers\Api\People;

use App\Actions\People\StudentParentManager;
use App\Http\Requests\People\StoreStudentParentRequest;
use App\Http\Requests\People\UpdateStudentParentRequest;
use App\Http\Resources\People\StudentParentResource;
use App\Models\Student;
use App\Models\StudentParent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StudentParentController
{
    public function index(int $student): JsonResponse
    {
        Gate::authorize('viewAny', StudentParent::class);

        return ApiResponse::data(StudentParentResource::collection(
            StudentParent::query()->where('student_id', $student)->orderByDesc('is_primary')->orderBy('id')->get(),
        )->resolve());
    }

    public function store(StoreStudentParentRequest $request, int $student, StudentParentManager $manager): JsonResponse
    {
        $student = Student::query()->findOrFail($student);
        Gate::authorize('create', StudentParent::class);

        return ApiResponse::data(
            (new StudentParentResource($manager->attach($student, $request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateStudentParentRequest $request, int $studentParent, StudentParentManager $manager): JsonResponse
    {
        $link = StudentParent::query()->findOrFail($studentParent);
        Gate::authorize('update', $link);

        return ApiResponse::data(
            (new StudentParentResource($manager->update($link, $request->validated())))->resolve($request),
        );
    }

    public function updateForStudent(UpdateStudentParentRequest $request, int $student, int $studentParent, StudentParentManager $manager): JsonResponse
    {
        $link = StudentParent::query()->where('student_id', $student)->findOrFail($studentParent);
        Gate::authorize('update', $link);

        return ApiResponse::data(
            (new StudentParentResource($manager->update($link, $request->validated())))->resolve($request),
        );
    }

    public function destroy(int $studentParent, StudentParentManager $manager): JsonResponse
    {
        $link = StudentParent::query()->findOrFail($studentParent);
        Gate::authorize('delete', $link);

        return ApiResponse::data((new StudentParentResource($manager->archive($link)))->resolve());
    }

    public function destroyForStudent(int $student, int $studentParent, StudentParentManager $manager): JsonResponse
    {
        $link = StudentParent::query()->where('student_id', $student)->findOrFail($studentParent);
        Gate::authorize('delete', $link);

        return ApiResponse::data((new StudentParentResource($manager->archive($link)))->resolve());
    }
}
