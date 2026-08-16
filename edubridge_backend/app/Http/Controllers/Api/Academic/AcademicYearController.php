<?php

namespace App\Http\Controllers\Api\Academic;

use App\Actions\Academic\AcademicStructureManager;
use App\Http\Requests\Academic\StoreAcademicYearRequest;
use App\Http\Requests\Academic\UpdateAcademicYearRequest;
use App\Http\Resources\Academic\AcademicYearResource;
use App\Models\AcademicYear;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AcademicYearController
{
    public function store(StoreAcademicYearRequest $request, AcademicStructureManager $manager): JsonResponse
    {
        Gate::authorize('create', AcademicYear::class);

        return ApiResponse::data(
            (new AcademicYearResource($manager->createYear($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateAcademicYearRequest $request, int $academicYear, AcademicStructureManager $manager): JsonResponse
    {
        $year = AcademicYear::query()->findOrFail($academicYear);
        Gate::authorize('update', $year);

        return ApiResponse::data(
            (new AcademicYearResource($manager->updateYear($year, $request->validated())))->resolve($request),
        );
    }

    public function destroy(int $academicYear, AcademicStructureManager $manager): JsonResponse
    {
        $year = AcademicYear::query()->findOrFail($academicYear);
        Gate::authorize('delete', $year);

        return ApiResponse::data(
            (new AcademicYearResource($manager->closeYear($year)))->resolve(),
        );
    }
}
