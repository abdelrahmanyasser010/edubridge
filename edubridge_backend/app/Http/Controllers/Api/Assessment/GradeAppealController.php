<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Actions\Assessment\DashboardGradeAppealReader;
use App\Actions\Assessment\GradeAppealManager;
use App\Http\Requests\Assessment\CorrectGradeAppealRequest;
use App\Http\Requests\Assessment\ReviewGradeAppealRequest;
use App\Http\Requests\Assessment\StoreGradeAppealRequest;
use App\Http\Requests\Dashboard\Assessment\DashboardGradeAppealFilterRequest;
use App\Http\Resources\Assessment\GradeAppealResource;
use App\Models\GradeAppeal;
use App\Models\GradeEntry;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GradeAppealController
{
    public function index(DashboardGradeAppealFilterRequest $request, DashboardGradeAppealReader $reader): JsonResponse
    {
        Gate::authorize('viewAny', GradeAppeal::class);
        $appeals = $reader->index($request->validated());

        return ApiResponse::data($appeals->items(), meta: [
            'pagination' => [
                'current_page' => $appeals->currentPage(),
                'per_page' => $appeals->perPage(),
                'total' => $appeals->total(),
                'last_page' => $appeals->lastPage(),
            ],
        ]);
    }

    public function store(StoreGradeAppealRequest $request, int $entry, GradeAppealManager $manager): JsonResponse
    {
        Gate::authorize('create', GradeAppeal::class);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data(
            (new GradeAppealResource($manager->create(GradeEntry::query()->findOrFail($entry), (int) $user->id, $request->validated('reason'))))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function approve(ReviewGradeAppealRequest $request, int $appeal, GradeAppealManager $manager): JsonResponse
    {
        $appeal = GradeAppeal::query()->findOrFail($appeal);
        Gate::authorize('review', $appeal);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new GradeAppealResource($manager->approve($appeal, (int) $user->id, $request->validated('review_note'))))->resolve($request));
    }

    public function reject(ReviewGradeAppealRequest $request, int $appeal, GradeAppealManager $manager): JsonResponse
    {
        $appeal = GradeAppeal::query()->findOrFail($appeal);
        Gate::authorize('review', $appeal);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new GradeAppealResource($manager->reject($appeal, (int) $user->id, $request->validated('review_note'))))->resolve($request));
    }

    public function correct(CorrectGradeAppealRequest $request, int $appeal, GradeAppealManager $manager): JsonResponse
    {
        $appeal = GradeAppeal::query()->findOrFail($appeal);
        Gate::authorize('correct', $appeal);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new GradeAppealResource($manager->correct($appeal, $request->validated(), (int) $user->id)))->resolve($request));
    }
}
