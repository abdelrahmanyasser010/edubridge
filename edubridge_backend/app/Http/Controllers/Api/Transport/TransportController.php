<?php

namespace App\Http\Controllers\Api\Transport;

use App\Actions\Transport\TransportManager;
use App\Http\Requests\Transport\AssignStudentToBusRouteRequest;
use App\Http\Requests\Transport\StoreBusOptOutRequest;
use App\Http\Requests\Transport\StoreBusRouteRequest;
use App\Http\Requests\Transport\StoreBusTrackingEventRequest;
use App\Http\Requests\Transport\StoreBusTripRequest;
use App\Http\Requests\Transport\StoreTransportAlertRequest;
use App\Http\Resources\Transport\BusRouteAssignmentResource;
use App\Http\Resources\Transport\BusRouteResource;
use App\Http\Resources\Transport\BusTrackingEventResource;
use App\Http\Resources\Transport\BusTripResource;
use App\Models\BusRoute;
use App\Models\BusTrip;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TransportController
{
    public function storeRoute(StoreBusRouteRequest $request, TransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');

        return ApiResponse::data((new BusRouteResource($manager->createRoute($request->validated())))->resolve($request), Response::HTTP_CREATED);
    }

    public function assignStudent(AssignStudentToBusRouteRequest $request, int $route, TransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');

        return ApiResponse::data((new BusRouteAssignmentResource($manager->assignStudent(BusRoute::query()->findOrFail($route), $request->validated())))->resolve($request), Response::HTTP_CREATED);
    }

    public function storeTrip(StoreBusTripRequest $request, int $route, TransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.manage');

        return ApiResponse::data((new BusTripResource($manager->createTrip(BusRoute::query()->findOrFail($route), $request->validated())))->resolve($request), Response::HTTP_CREATED);
    }

    public function ingestTracking(StoreBusTrackingEventRequest $request, int $trip, TransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.track');

        return ApiResponse::data((new BusTrackingEventResource($manager->ingestTracking(BusTrip::query()->findOrFail($trip), $request->validated())))->resolve($request), Response::HTTP_CREATED);
    }

    public function parentLiveStatus(Request $request, int $student, TransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.view');
        $user = $request->user() ?? throw new AuthenticationException;
        return ApiResponse::data(
            $manager->liveStatusForParent(Student::query()->findOrFail($student), (int) $user->id),
        );
    }

    public function optOut(StoreBusOptOutRequest $request, int $student, TransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.view');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->optOut(Student::query()->findOrFail($student), (int) $user->id, $request->validated())->toArray(), Response::HTTP_CREATED);
    }

    public function alert(StoreTransportAlertRequest $request, int $route, TransportManager $manager): JsonResponse
    {
        Gate::authorize('transport.alert');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->alert(BusRoute::query()->findOrFail($route), $request->validated(), (int) $user->id)->toArray(), Response::HTTP_CREATED);
    }
}
