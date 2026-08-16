<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Operations\DashboardLeavePermitReader;
use App\Http\Requests\Dashboard\LeavePermits\DashboardLeavePermitFilterRequest;
use App\Http\Resources\Dashboard\LeavePermitListResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class LeavePermitDashboardController
{
    public function index(DashboardLeavePermitFilterRequest $request, DashboardLeavePermitReader $reader): JsonResponse
    {
        Gate::authorize('operations.view');
        $permits = $reader->permits($request->validated());

        return ApiResponse::data(
            LeavePermitListResource::collection($permits->items())->resolve($request),
            meta: $this->paginationMeta($permits),
        );
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
