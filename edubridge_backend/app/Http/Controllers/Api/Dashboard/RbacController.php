<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Rbac\DashboardRbacManager;
use App\Http\Requests\Dashboard\Rbac\StoreDashboardAdminAccountRequest;
use App\Http\Requests\Dashboard\Rbac\StoreDashboardRoleRequest;
use App\Http\Requests\Dashboard\Rbac\UpdateDashboardAdminRoleRequest;
use App\Http\Requests\Dashboard\Rbac\UpdateDashboardAdminStatusRequest;
use App\Http\Requests\Dashboard\Rbac\UpdateDashboardRbacMatrixRequest;
use App\Http\Resources\Dashboard\RbacResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RbacController
{
    public function roles(DashboardRbacManager $manager): JsonResponse
    {
        Gate::authorize('rbac.view');

        return ApiResponse::data(RbacResource::collection($manager->roles())->resolve());
    }

    public function storeRole(StoreDashboardRoleRequest $request, DashboardRbacManager $manager): JsonResponse
    {
        Gate::authorize('rbac.manage');

        return ApiResponse::data(
            (new RbacResource($manager->createRole($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function permissions(DashboardRbacManager $manager): JsonResponse
    {
        Gate::authorize('rbac.view');

        return ApiResponse::data(RbacResource::collection($manager->permissions())->resolve());
    }

    public function matrix(DashboardRbacManager $manager): JsonResponse
    {
        Gate::authorize('rbac.view');

        return ApiResponse::data($manager->matrix());
    }

    public function updateMatrix(UpdateDashboardRbacMatrixRequest $request, DashboardRbacManager $manager): JsonResponse
    {
        Gate::authorize('rbac.manage');

        return ApiResponse::data($manager->updateMatrix($request->validated()));
    }

    public function adminAccounts(DashboardRbacManager $manager): JsonResponse
    {
        Gate::authorize('rbac.view');

        return ApiResponse::data(RbacResource::collection($manager->adminAccounts())->resolve());
    }

    public function storeAdminAccount(StoreDashboardAdminAccountRequest $request, DashboardRbacManager $manager): JsonResponse
    {
        Gate::authorize('rbac.manage');

        return ApiResponse::data(
            (new RbacResource($manager->createAdminAccount($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function updateAdminRole(UpdateDashboardAdminRoleRequest $request, int $account, DashboardRbacManager $manager): JsonResponse
    {
        Gate::authorize('rbac.manage');

        return ApiResponse::data(
            (new RbacResource($manager->updateAdminRole($account, (string) $request->validated('role_key'))))->resolve($request),
        );
    }

    public function updateAdminStatus(UpdateDashboardAdminStatusRequest $request, int $account, DashboardRbacManager $manager): JsonResponse
    {
        Gate::authorize('rbac.manage');

        return ApiResponse::data(
            (new RbacResource($manager->updateAdminStatus($account, (string) $request->validated('status'))))->resolve($request),
        );
    }
}
