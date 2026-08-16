<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Mobile\ParentActivityManager;
use App\Http\Requests\Mobile\ActivityFilterRequest;
use App\Http\Resources\Mobile\ActivityResource;
use App\Models\SchoolActivity;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

class ParentActivityController
{
    public function index(ActivityFilterRequest $request, int $student, ParentActivityManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $activities = $manager->list(Student::query()->findOrFail($student), (int) $user->id, $request->validated());

        return ApiResponse::data(
            ActivityResource::collection($activities->items())->resolve($request),
            meta: $this->paginationMeta($activities),
        );
    }

    public function show(Request $request, int $student, int $activity, ParentActivityManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $model = $manager->detail(
            Student::query()->findOrFail($student),
            SchoolActivity::query()->findOrFail($activity),
            (int) $user->id,
        );

        return ApiResponse::data((new ActivityResource($model))->resolve($request));
    }

    public function register(Request $request, int $student, int $activity, ParentActivityManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $registration = $manager->register(
            Student::query()->findOrFail($student),
            SchoolActivity::query()->findOrFail($activity),
            (int) $user->id,
        );

        return ApiResponse::data([
            'registration_id' => (string) $registration->id,
            'status' => $registration->status,
            'invoice_id' => $registration->finance_invoice_id === null ? null : (string) $registration->finance_invoice_id,
            'registered_at' => $registration->registered_at?->toJSON(),
        ], Response::HTTP_CREATED);
    }

    public function cancel(Request $request, int $student, int $activity, ParentActivityManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $registration = $manager->cancel(
            Student::query()->findOrFail($student),
            SchoolActivity::query()->findOrFail($activity),
            (int) $user->id,
        );

        return ApiResponse::data([
            'registration_id' => (string) $registration->id,
            'status' => $registration->status,
            'cancelled_at' => $registration->cancelled_at?->toJSON(),
        ]);
    }

    /** @return array<string, mixed> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return ['pagination' => [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]];
    }
}
