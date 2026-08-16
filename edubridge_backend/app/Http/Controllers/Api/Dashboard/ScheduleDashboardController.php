<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Academic\DashboardScheduleReader;
use App\Http\Requests\Dashboard\Schedules\DashboardScheduleConflictCheckRequest;
use App\Http\Requests\Dashboard\Schedules\DashboardScheduleFilterRequest;
use App\Http\Requests\Dashboard\Schedules\DashboardScheduleGlobalConflictRequest;
use App\Http\Resources\Dashboard\ScheduleDashboardResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ScheduleDashboardController
{
    public function index(DashboardScheduleFilterRequest $request, DashboardScheduleReader $reader): JsonResponse
    {
        Gate::authorize('schedule.view');
        $schedules = $reader->schedules($request->validated());

        return ApiResponse::data(
            ScheduleDashboardResource::collection($schedules->items())->resolve($request),
            meta: $this->paginationMeta($schedules),
        );
    }

    public function conflictCheck(DashboardScheduleConflictCheckRequest $request, DashboardScheduleReader $reader): JsonResponse
    {
        Gate::authorize('schedule.manage');

        return ApiResponse::data($reader->conflictCheck($request->validated()));
    }


    public function globalConflictCheck(DashboardScheduleGlobalConflictRequest $request, DashboardScheduleReader $reader): JsonResponse
    {
        Gate::authorize('schedule.view');

        return ApiResponse::data($reader->globalConflictCheck((int) $request->validated('academic_term_id')));
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
