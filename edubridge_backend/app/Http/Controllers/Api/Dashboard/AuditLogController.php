<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\AuditLogs\DashboardAuditLogManager;
use App\Http\Requests\Dashboard\AuditLogs\AuditLogFilterRequest;
use App\Http\Resources\Dashboard\AuditLogResource;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class AuditLogController
{
    public function index(AuditLogFilterRequest $request, DashboardAuditLogManager $manager): JsonResponse
    {
        Gate::authorize('audit.view');
        $logs = $manager->logs($request->validated());

        return ApiResponse::data(
            AuditLogResource::collection($logs->items())->resolve($request),
            meta: $this->paginationMeta($logs),
        );
    }

    public function show(int $auditLog, DashboardAuditLogManager $manager): JsonResponse
    {
        Gate::authorize('audit.view');

        return ApiResponse::data(
            (new AuditLogResource($manager->log(AuditLog::query()->findOrFail($auditLog))))->resolve(),
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
