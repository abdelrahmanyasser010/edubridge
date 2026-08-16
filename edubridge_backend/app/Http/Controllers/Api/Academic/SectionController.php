<?php

namespace App\Http\Controllers\Api\Academic;

use App\Actions\Academic\AcademicStructureManager;
use App\Http\Requests\Academic\StoreSectionRequest;
use App\Http\Requests\Academic\UpdateSectionRequest;
use App\Http\Resources\Academic\SectionResource;
use App\Models\Section;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SectionController
{
    public function store(StoreSectionRequest $request, AcademicStructureManager $manager): JsonResponse
    {
        Gate::authorize('create', Section::class);

        return ApiResponse::data(
            (new SectionResource($manager->createSection($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateSectionRequest $request, int $section, AcademicStructureManager $manager): JsonResponse
    {
        $section = Section::query()->findOrFail($section);
        Gate::authorize('update', $section);

        return ApiResponse::data(
            (new SectionResource($manager->updateSection($section, $request->validated())))->resolve($request),
        );
    }

    public function destroy(int $section, AcademicStructureManager $manager): JsonResponse
    {
        $section = Section::query()->findOrFail($section);
        Gate::authorize('delete', $section);

        return ApiResponse::data(
            (new SectionResource($manager->archiveSection($section)))->resolve(),
        );
    }
}
