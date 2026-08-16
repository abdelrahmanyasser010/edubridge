<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Assessment\DashboardAssessmentReader;
use App\Actions\Assessment\DashboardGradeEntryEditor;
use App\Http\Requests\Dashboard\Assessments\DashboardAssessmentFilterRequest;
use App\Http\Requests\Dashboard\Assessments\DashboardUpdateGradeEntriesRequest;
use App\Http\Resources\Assessment\GradeEntryResource;
use App\Http\Resources\Dashboard\AssessmentDashboardResource;
use App\Models\Assessment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class AssessmentDashboardController
{
    public function index(DashboardAssessmentFilterRequest $request, DashboardAssessmentReader $reader): JsonResponse
    {
        Gate::authorize('grade.view');
        $assessments = $reader->assessments($request->validated());

        return ApiResponse::data(
            AssessmentDashboardResource::collection($assessments->items())->resolve($request),
            meta: $this->paginationMeta($assessments),
        );
    }

    public function show(int $assessment, DashboardAssessmentReader $reader): JsonResponse
    {
        Gate::authorize('grade.view');

        return ApiResponse::data((new AssessmentDashboardResource($reader->assessment($assessment)))->resolve(request()));
    }

    public function updateGrades(DashboardUpdateGradeEntriesRequest $request, int $assessment, DashboardGradeEntryEditor $editor): JsonResponse
    {
        Gate::authorize('grade.approve');
        $assessment = Assessment::query()->findOrFail($assessment);

        return ApiResponse::data(GradeEntryResource::collection(
            $editor->update($assessment, $request->validated())
        )->resolve($request));
    }

    /** @return array<string, mixed> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
