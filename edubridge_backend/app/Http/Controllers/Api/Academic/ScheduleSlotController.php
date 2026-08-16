<?php

namespace App\Http\Controllers\Api\Academic;

use App\Actions\Academic\ScheduleManager;
use App\Http\Requests\Academic\StoreScheduleSlotRequest;
use App\Http\Requests\Academic\UpdateScheduleSlotRequest;
use App\Http\Resources\Academic\ScheduleSlotResource;
use App\Models\AcademicTerm;
use App\Models\ScheduleSlot;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ScheduleSlotController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', ScheduleSlot::class);

        return ApiResponse::data(ScheduleSlotResource::collection(
            ScheduleSlot::query()->orderBy('weekday')->orderBy('starts_at')->get(),
        )->resolve());
    }

    public function store(StoreScheduleSlotRequest $request, ScheduleManager $manager): JsonResponse
    {
        Gate::authorize('create', ScheduleSlot::class);

        return ApiResponse::data(
            (new ScheduleSlotResource($manager->createSlot($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateScheduleSlotRequest $request, int $slot, ScheduleManager $manager): JsonResponse
    {
        $slot = ScheduleSlot::query()->findOrFail($slot);
        Gate::authorize('update', $slot);

        return ApiResponse::data((new ScheduleSlotResource($manager->updateSlot($slot, $request->validated())))->resolve($request));
    }

    public function destroy(int $slot, ScheduleManager $manager): JsonResponse
    {
        $slot = ScheduleSlot::query()->findOrFail($slot);
        Gate::authorize('delete', $slot);

        return ApiResponse::data((new ScheduleSlotResource($manager->archiveSlot($slot)))->resolve());
    }

    public function generate(int $academicTerm, ScheduleManager $manager): JsonResponse
    {
        $term = AcademicTerm::query()->findOrFail($academicTerm);
        Gate::authorize('create', ScheduleSlot::class);

        return ApiResponse::data($manager->generateSessions($term));
    }
}
