<?php

namespace App\Http\Controllers\Api\Academic;

use App\Actions\Academic\AcademicStructureManager;
use App\Http\Requests\Academic\StoreAcademicTermRequest;
use App\Http\Requests\Academic\UpdateAcademicTermRequest;
use App\Http\Resources\Academic\AcademicTermResource;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AcademicTermController
{
    public function store(StoreAcademicTermRequest $request, int $academicYear, AcademicStructureManager $manager): JsonResponse
    {
        $year = AcademicYear::query()->findOrFail($academicYear);
        Gate::authorize('create', AcademicTerm::class);

        return ApiResponse::data(
            (new AcademicTermResource($manager->createTerm($year, $request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateAcademicTermRequest $request, int $academicTerm, AcademicStructureManager $manager): JsonResponse
    {
        $term = AcademicTerm::query()->findOrFail($academicTerm);
        Gate::authorize('update', $term);

        return ApiResponse::data(
            (new AcademicTermResource($manager->updateTerm($term, $request->validated())))->resolve($request),
        );
    }

    public function activate(int $academicTerm, AcademicStructureManager $manager): JsonResponse
    {
        $term = AcademicTerm::query()->findOrFail($academicTerm);
        Gate::authorize('update', $term);

        return ApiResponse::data(
            (new AcademicTermResource($manager->activateTerm($term)))->resolve(),
        );
    }
}
