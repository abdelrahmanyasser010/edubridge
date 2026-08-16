<?php

namespace App\Http\Controllers\Api\People;

use App\Http\Requests\People\StoreResidentialAreaRequest;
use App\Http\Resources\People\ResidentialAreaResource;
use App\Models\ResidentialArea;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ResidentialAreaController
{
    public function index(): JsonResponse
    {
        Gate::authorize('people.view');

        return ApiResponse::data(ResidentialAreaResource::collection(
            ResidentialArea::query()
                ->where('status', ResidentialArea::STATUS_ACTIVE)
                ->orderBy('city')
                ->orderBy('name')
                ->get(),
        )->resolve());
    }

    public function store(StoreResidentialAreaRequest $request): JsonResponse
    {
        Gate::authorize('people.manage');

        $area = ResidentialArea::query()->create([
            'city' => trim((string) $request->validated('city')),
            'name' => trim((string) $request->validated('name')),
            'status' => ResidentialArea::STATUS_ACTIVE,
        ]);

        return ApiResponse::data(
            (new ResidentialAreaResource($area->refresh()))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function destroy(int $residentialArea): JsonResponse
    {
        Gate::authorize('people.manage');

        $area = ResidentialArea::query()->findOrFail($residentialArea);
        $area->forceFill(['status' => ResidentialArea::STATUS_ARCHIVED])->save();

        return ApiResponse::data((new ResidentialAreaResource($area->refresh()))->resolve());
    }
}
