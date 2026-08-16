<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Dashboard\DashboardCanvasConfigManager;
use App\Http\Requests\Dashboard\Canvas\DashboardCanvasConfigKeyRequest;
use App\Http\Requests\Dashboard\Canvas\SaveDashboardCanvasConfigRequest;
use App\Http\Resources\Dashboard\DashboardCanvasConfigResource;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardCanvasConfigController
{
    public function show(DashboardCanvasConfigKeyRequest $request, string $key, DashboardCanvasConfigManager $manager): JsonResponse
    {
        Gate::authorize('settings.view');

        return ApiResponse::data((new DashboardCanvasConfigResource($manager->get($key)))->resolve($request));
    }

    public function save(SaveDashboardCanvasConfigRequest $request, string $key, DashboardCanvasConfigManager $manager): JsonResponse
    {
        Gate::authorize('settings.manage');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new DashboardCanvasConfigResource($manager->save($key, $request->validated(), (int) $user->id)))->resolve($request));
    }
}
