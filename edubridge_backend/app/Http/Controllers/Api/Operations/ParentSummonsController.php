<?php

namespace App\Http\Controllers\Api\Operations;

use App\Actions\Operations\ParentSummonsManager;
use App\Http\Requests\Operations\RespondParentSummonsRequest;
use App\Http\Requests\Operations\StoreParentSummonsRequest;
use App\Http\Resources\Operations\ParentSummonsResource;
use App\Models\Guardian;
use App\Models\ParentSummons;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ParentSummonsController
{
    public function dashboardIndex(Request $request): JsonResponse
    {
        Gate::authorize('operations.view');

        $summons = ParentSummons::query()
            ->latest('scheduled_at')
            ->limit(250)
            ->get();

        return ApiResponse::data(ParentSummonsResource::collection($summons)->resolve($request));
    }

    public function store(StoreParentSummonsRequest $request, ParentSummonsManager $manager): JsonResponse
    {
        Gate::authorize('create', ParentSummons::class);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data(
            (new ParentSummonsResource($manager->create($request->validated(), (int) $user->id)))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function parentIndex(Request $request, int $student): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $student = Student::query()->findOrFail($student);
        Gate::authorize('viewForParent', [ParentSummons::class, $student]);
        $parentId = Guardian::query()->where('central_user_id', $user->id)->value('id');

        return ApiResponse::data(ParentSummonsResource::collection(
            ParentSummons::query()
                ->where('student_id', $student->id)
                ->where('parent_id', $parentId)
                ->latest('scheduled_at')
                ->get()
        )->resolve($request));
    }

    public function respond(RespondParentSummonsRequest $request, int $summons, ParentSummonsManager $manager): JsonResponse
    {
        $summons = ParentSummons::query()->findOrFail($summons);
        Gate::authorize('respond', $summons);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new ParentSummonsResource($manager->respond($summons, (int) $user->id, $request->validated())))->resolve($request));
    }
}
