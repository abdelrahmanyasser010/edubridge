<?php

namespace App\Http\Controllers\Api\People;

use App\Actions\People\PeopleProfileManager;
use App\Http\Requests\People\StoreGuardianRequest;
use App\Http\Requests\People\UpdateGuardianRequest;
use App\Http\Resources\People\GuardianResource;
use App\Models\Guardian;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GuardianController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Guardian::class);

        return ApiResponse::data(GuardianResource::collection(
            Guardian::query()->orderBy('full_name')->get(),
        )->resolve());
    }

    public function store(StoreGuardianRequest $request, PeopleProfileManager $manager): JsonResponse
    {
        Gate::authorize('create', Guardian::class);

        return ApiResponse::data(
            (new GuardianResource($manager->createGuardian($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function show(int $parent): JsonResponse
    {
        $guardian = Guardian::query()->findOrFail($parent);
        Gate::authorize('view', $guardian);

        return ApiResponse::data((new GuardianResource($guardian))->resolve());
    }

    public function update(UpdateGuardianRequest $request, int $parent, PeopleProfileManager $manager): JsonResponse
    {
        $guardian = Guardian::query()->findOrFail($parent);
        Gate::authorize('update', $guardian);

        return ApiResponse::data(
            (new GuardianResource($manager->updateGuardian($guardian, $request->validated())))->resolve($request),
        );
    }

    public function destroy(int $parent, PeopleProfileManager $manager): JsonResponse
    {
        $guardian = Guardian::query()->findOrFail($parent);
        Gate::authorize('delete', $guardian);

        return ApiResponse::data((new GuardianResource($manager->archiveGuardian($guardian)))->resolve());
    }
}
