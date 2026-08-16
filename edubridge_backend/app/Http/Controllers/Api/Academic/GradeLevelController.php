<?php

namespace App\Http\Controllers\Api\Academic;

use App\Actions\Academic\AcademicStructureManager;
use App\Http\Requests\Academic\StoreGradeLevelRequest;
use App\Http\Requests\Academic\UpdateGradeLevelRequest;
use App\Http\Resources\Academic\GradeLevelResource;
use App\Models\GradeLevel;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GradeLevelController
{
    public function store(StoreGradeLevelRequest $request, AcademicStructureManager $manager): JsonResponse
    {
        Gate::authorize('create', GradeLevel::class);

        return ApiResponse::data(
            (new GradeLevelResource($manager->createGradeLevel($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateGradeLevelRequest $request, int $gradeLevel, AcademicStructureManager $manager): JsonResponse
    {
        $gradeLevel = GradeLevel::query()->findOrFail($gradeLevel);
        Gate::authorize('update', $gradeLevel);

        return ApiResponse::data(
            (new GradeLevelResource($manager->updateGradeLevel($gradeLevel, $request->validated())))->resolve($request),
        );
    }

    public function destroy(int $gradeLevel, AcademicStructureManager $manager): JsonResponse
    {
        $gradeLevel = GradeLevel::query()->findOrFail($gradeLevel);
        Gate::authorize('delete', $gradeLevel);

        return ApiResponse::data(
            (new GradeLevelResource($manager->archiveGradeLevel($gradeLevel)))->resolve(),
        );
    }
}
