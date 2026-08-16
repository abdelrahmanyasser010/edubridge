<?php

namespace App\Http\Controllers\Api\Academic;

use App\Actions\Academic\AcademicStructureManager;
use App\Http\Requests\Academic\StoreSubjectRequest;
use App\Http\Requests\Academic\UpdateSubjectRequest;
use App\Http\Resources\Academic\SubjectResource;
use App\Models\Subject;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SubjectController
{
    public function store(StoreSubjectRequest $request, AcademicStructureManager $manager): JsonResponse
    {
        Gate::authorize('create', Subject::class);

        return ApiResponse::data(
            (new SubjectResource($manager->createSubject($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateSubjectRequest $request, int $subject, AcademicStructureManager $manager): JsonResponse
    {
        $subject = Subject::query()->findOrFail($subject);
        Gate::authorize('update', $subject);

        return ApiResponse::data(
            (new SubjectResource($manager->updateSubject($subject, $request->validated())))->resolve($request),
        );
    }

    public function destroy(int $subject, AcademicStructureManager $manager): JsonResponse
    {
        $subject = Subject::query()->findOrFail($subject);
        Gate::authorize('delete', $subject);

        return ApiResponse::data(
            (new SubjectResource($manager->archiveSubject($subject)))->resolve(),
        );
    }
}
