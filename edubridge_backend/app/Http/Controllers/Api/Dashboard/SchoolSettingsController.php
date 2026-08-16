<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\SchoolSettings\DashboardSchoolSettingsManager;
use App\Http\Requests\Dashboard\SchoolSettings\UpdateIntegrationSettingRequest;
use App\Http\Requests\Dashboard\SchoolSettings\UpdateSchoolSettingsRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SchoolSettingsController
{
    public function settings(DashboardSchoolSettingsManager $manager): JsonResponse
    {
        Gate::authorize('settings.view');

        return ApiResponse::data($manager->settings());
    }

    public function updateSettings(UpdateSchoolSettingsRequest $request, DashboardSchoolSettingsManager $manager): JsonResponse
    {
        Gate::authorize('settings.manage');

        return ApiResponse::data($manager->updateSettings($request->validated()));
    }

    public function integrations(DashboardSchoolSettingsManager $manager): JsonResponse
    {
        Gate::authorize('integrations.view');

        return ApiResponse::data($manager->integrations());
    }

    public function updateIntegration(UpdateIntegrationSettingRequest $request, string $integration, DashboardSchoolSettingsManager $manager): JsonResponse
    {
        Gate::authorize('integrations.manage');

        return ApiResponse::data($manager->updateIntegration($integration, $request->validated()));
    }

    public function testIntegration(string $integration, DashboardSchoolSettingsManager $manager): JsonResponse
    {
        Gate::authorize('integrations.manage');

        return ApiResponse::data($manager->testIntegration($integration));
    }
}
