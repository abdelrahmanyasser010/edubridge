<?php

namespace App\Http\Controllers\Api\Academic;

use App\Actions\Academic\TeacherSectionSubjectManager;
use App\Http\Requests\Academic\StoreTeacherSectionSubjectRequest;
use App\Http\Requests\Academic\UpdateTeacherSectionSubjectRequest;
use App\Http\Resources\Academic\TeacherSectionSubjectResource;
use App\Models\TeacherSectionSubject;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TeacherSectionSubjectController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', TeacherSectionSubject::class);

        return ApiResponse::data(TeacherSectionSubjectResource::collection(
            TeacherSectionSubject::query()->orderBy('id')->get(),
        )->resolve());
    }

    public function store(StoreTeacherSectionSubjectRequest $request, TeacherSectionSubjectManager $manager): JsonResponse
    {
        Gate::authorize('create', TeacherSectionSubject::class);

        return ApiResponse::data(
            (new TeacherSectionSubjectResource($manager->create($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateTeacherSectionSubjectRequest $request, int $allocation, TeacherSectionSubjectManager $manager): JsonResponse
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($allocation);
        Gate::authorize('update', $allocation);

        return ApiResponse::data(
            (new TeacherSectionSubjectResource($manager->update($allocation, $request->validated())))->resolve($request),
        );
    }

    public function destroy(int $allocation, TeacherSectionSubjectManager $manager): JsonResponse
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($allocation);
        Gate::authorize('delete', $allocation);

        return ApiResponse::data((new TeacherSectionSubjectResource($manager->archive($allocation)))->resolve());
    }
}
