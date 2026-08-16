<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Reporting\DashboardGradeExportManager;
use App\Http\Resources\Dashboard\ReportExportResource;
use App\Models\Assessment;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReportExportController
{
    public function storeAssessmentExport(Request $request, int $assessment, DashboardGradeExportManager $manager): JsonResponse
    {
        Gate::authorize('grade.view');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data(
            (new ReportExportResource($manager->requestAssessmentExport(Assessment::query()->findOrFail($assessment), (int) $user->id)))->resolve($request),
            Response::HTTP_ACCEPTED,
        );
    }

    public function show(Request $request, string $export, DashboardGradeExportManager $manager): JsonResponse
    {
        Gate::authorize('grade.view');

        return ApiResponse::data((new ReportExportResource($manager->export($export)))->resolve($request));
    }
}
