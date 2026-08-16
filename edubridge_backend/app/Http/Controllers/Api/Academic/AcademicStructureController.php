<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Resources\Academic\AcademicYearResource;
use App\Http\Resources\Academic\GradeLevelResource;
use App\Http\Resources\Academic\SubjectResource;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AcademicStructureController
{
    public function __invoke(): JsonResponse
    {
        Gate::authorize('viewAny', AcademicYear::class);
        Gate::authorize('viewAny', GradeLevel::class);
        Gate::authorize('viewAny', Subject::class);

        return ApiResponse::data([
            'academic_years' => AcademicYearResource::collection(
                AcademicYear::query()->with('terms')->orderByDesc('starts_on')->get(),
            )->resolve(),
            'grade_levels' => GradeLevelResource::collection(
                GradeLevel::query()->with(['sections', 'subjects'])->orderBy('sort_order')->orderBy('id')->get(),
            )->resolve(),
            'subjects' => SubjectResource::collection(
                Subject::query()->orderBy('name')->get(),
            )->resolve(),
        ]);
    }
}
