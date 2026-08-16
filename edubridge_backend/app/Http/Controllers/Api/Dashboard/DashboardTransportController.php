<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Transport\DashboardTransportManager;
use App\Http\Requests\Dashboard\Transport\DashboardAssignStudentToBusRouteRequest;
use App\Http\Requests\Dashboard\Transport\DashboardContactDriverLogRequest;
use App\Http\Requests\Dashboard\Transport\DashboardDelayAlertRequest;
use App\Http\Requests\Dashboard\Transport\DashboardStoreBusRouteRequest;
use App\Http\Requests\Dashboard\Transport\DashboardTransportListRequest;
use App\Http\Requests\Dashboard\Transport\DashboardUpdateBusRouteAssignmentRequest;
use App\Http\Requests\Dashboard\Transport\DashboardUpdateBusRouteRequest;
use App\Http\Resources\Transport\BusRouteAssignmentResource;
use App\Models\BusRoute;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class DashboardTransportController
{
    public function summary(DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.view');

        return ApiResponse::data($manager->summary());
    }

    public function routes(DashboardTransportListRequest $request, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.view');
        $routes = $manager->routes($request->validated());

        return ApiResponse::data($routes->items(), meta: $this->paginationMeta($routes));
    }

    public function storeRoute(DashboardStoreBusRouteRequest $request, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');

        return ApiResponse::data($manager->createRoute($request->validated()), Response::HTTP_CREATED);
    }

    public function showRoute(int $route, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.view');

        return ApiResponse::data($manager->route(BusRoute::query()->findOrFail($route)));
    }

    public function updateRoute(DashboardUpdateBusRouteRequest $request, int $route, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');

        return ApiResponse::data($manager->updateRoute(BusRoute::query()->findOrFail($route), $request->validated()));
    }

    public function archiveRoute(int $route, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');

        return ApiResponse::data($manager->archiveRoute(BusRoute::query()->findOrFail($route)));
    }

    public function assignStudent(DashboardAssignStudentToBusRouteRequest $request, int $route, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');
        $assignment = $manager->assignStudent(BusRoute::query()->findOrFail($route), $request->validated());

        return ApiResponse::data((new BusRouteAssignmentResource($assignment))->resolve($request), Response::HTTP_CREATED);
    }

    public function updateAssignment(DashboardUpdateBusRouteAssignmentRequest $request, int $route, int $assignment, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');
        $updated = $manager->updateAssignment(BusRoute::query()->findOrFail($route), $assignment, $request->validated());

        return ApiResponse::data((new BusRouteAssignmentResource($updated))->resolve($request));
    }

    public function archiveAssignment(int $route, int $assignment, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');
        $archived = $manager->archiveAssignment(BusRoute::query()->findOrFail($route), $assignment);

        return ApiResponse::data((new BusRouteAssignmentResource($archived))->resolve(request()));
    }

    public function passengers(int $route, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.view');

        return ApiResponse::data($manager->passengers(BusRoute::query()->findOrFail($route)));
    }

    public function events(int $route, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.view');

        return ApiResponse::data($manager->events(BusRoute::query()->findOrFail($route)));
    }

    public function delayAlert(DashboardDelayAlertRequest $request, int $route, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.alerts.send');
        $user = $request->user() ?? throw new AuthenticationException;
        $alert = $manager->delayAlert(BusRoute::query()->findOrFail($route), $request->validated(), (int) $user->id);

        return ApiResponse::data([
            'id' => (string) $alert->id,
            'route_id' => (string) $alert->bus_route_id,
            'type' => $alert->type,
            'message' => $alert->message,
            'delay_minutes' => $alert->delay_minutes,
            'channels' => $alert->channels,
        ], Response::HTTP_CREATED);
    }

    public function contactDriverLog(DashboardContactDriverLogRequest $request, int $route, DashboardTransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');
        $user = $request->user() ?? throw new AuthenticationException;
        $log = $manager->contactDriverLog(BusRoute::query()->findOrFail($route), $request->validated(), (int) $user->id);

        return ApiResponse::data([
            'id' => (string) $log->id,
            'route_id' => (string) $log->bus_route_id,
            'driver_phone' => $log->driver_phone,
            'outcome' => $log->outcome,
            'notes' => $log->notes,
        ], Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
