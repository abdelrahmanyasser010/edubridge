<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Behavior\DashboardBehaviorNoteReader;
use App\Http\Requests\Dashboard\Behavior\DashboardBehaviorNoteFilterRequest;
use App\Http\Resources\Dashboard\BehaviorNoteListResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class BehaviorNoteDashboardController
{
    public function index(DashboardBehaviorNoteFilterRequest $request, DashboardBehaviorNoteReader $reader): JsonResponse
    {
        Gate::authorize('behavior.view');
        $notes = $reader->notes($request->validated());

        return ApiResponse::data(
            BehaviorNoteListResource::collection($notes->items())->resolve($request),
            meta: $this->paginationMeta($notes),
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
