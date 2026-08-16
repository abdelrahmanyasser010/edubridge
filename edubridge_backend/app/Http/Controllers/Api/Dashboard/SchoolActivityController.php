<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Operations\SchoolActivityManager;
use App\Http\Requests\Dashboard\Activities\DashboardActivityFilterRequest;
use App\Http\Requests\Dashboard\Activities\StoreDashboardActivityRequest;
use App\Http\Requests\Dashboard\Activities\UpdateDashboardActivityRequest;
use App\Http\Resources\Mobile\ActivityResource;
use App\Models\SchoolActivity;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class SchoolActivityController
{
    public function index(DashboardActivityFilterRequest $request, SchoolActivityManager $manager): JsonResponse
    {
        Gate::authorize('academic.view');
        $paginator = $manager->list($request->validated());

        return ApiResponse::data(
            ActivityResource::collection($paginator->items())->resolve($request),
            meta: $this->paginationMeta($paginator),
        );
    }

    public function store(StoreDashboardActivityRequest $request, SchoolActivityManager $manager): JsonResponse
    {
        Gate::authorize('academic.manage');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new ActivityResource($manager->create($request->validated(), (int) $user->id)))->resolve($request), Response::HTTP_CREATED);
    }

    public function show(Request $request, int $activity): JsonResponse
    {
        Gate::authorize('academic.view');
        $model = SchoolActivity::query()->with('registrations.student')->withCount(['registrations as active_registrations_count' => fn ($query) => $query->where('status', '!=', 'cancelled')])->findOrFail($activity);

        $activityForResource = clone $model;
        $activityForResource->unsetRelation('registrations');

        return ApiResponse::data([
            'activity' => (new ActivityResource($activityForResource))->resolve($request),
            'registrations' => $model->registrations->map(fn ($registration): array => [
                'id' => (string) $registration->id,
                'status' => $registration->status,
                'student' => $registration->student === null ? null : [
                    'id' => (string) $registration->student->id,
                    'full_name' => $registration->student->full_name,
                    'admission_number' => $registration->student->admission_number,
                ],
                'invoice_id' => $registration->finance_invoice_id === null ? null : (string) $registration->finance_invoice_id,
                'registered_at' => $registration->registered_at?->toJSON(),
            ])->values()->all(),
        ]);
    }

    public function update(UpdateDashboardActivityRequest $request, int $activity, SchoolActivityManager $manager): JsonResponse
    {
        Gate::authorize('academic.manage');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new ActivityResource($manager->update(SchoolActivity::query()->findOrFail($activity), $request->validated(), (int) $user->id)))->resolve($request));
    }

    public function destroy(Request $request, int $activity, SchoolActivityManager $manager): JsonResponse
    {
        Gate::authorize('academic.manage');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new ActivityResource($manager->cancel(SchoolActivity::query()->findOrFail($activity), (int) $user->id)))->resolve($request));
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return ['pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()]];
    }
}
