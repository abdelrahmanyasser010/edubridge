<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Broadcasts\DashboardBroadcastManager;
use App\Http\Requests\Dashboard\Broadcasts\DashboardBroadcastFilterRequest;
use App\Http\Requests\Dashboard\Broadcasts\StoreDashboardBroadcastRequest;
use App\Http\Resources\Dashboard\BroadcastResource;
use App\Models\BroadcastMessage;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class BroadcastController
{
    public function index(DashboardBroadcastFilterRequest $request, DashboardBroadcastManager $manager): JsonResponse
    {
        Gate::authorize('broadcasts.view');
        $broadcasts = $manager->broadcasts($request->validated());

        return ApiResponse::data(
            BroadcastResource::collection($broadcasts->items())->resolve($request),
            meta: $this->paginationMeta($broadcasts),
        );
    }

    public function store(StoreDashboardBroadcastRequest $request, DashboardBroadcastManager $manager): JsonResponse
    {
        $data = $request->validated();
        Gate::authorize(isset($data['scheduled_at']) ? 'broadcasts.schedule' : 'broadcasts.send');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data(
            (new BroadcastResource($manager->create($data, (int) $user->id)))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function show(int $broadcast, DashboardBroadcastManager $manager): JsonResponse
    {
        Gate::authorize('broadcasts.view');

        return ApiResponse::data(
            (new BroadcastResource($manager->broadcast(BroadcastMessage::query()->findOrFail($broadcast))))->resolve(),
        );
    }

    public function send(int $broadcast, DashboardBroadcastManager $manager): JsonResponse
    {
        Gate::authorize('broadcasts.send');
        $user = request()->user() ?? throw new AuthenticationException;

        return ApiResponse::data(
            (new BroadcastResource($manager->send(BroadcastMessage::query()->findOrFail($broadcast), (int) $user->id)))->resolve(),
        );
    }

    public function cancel(int $broadcast, DashboardBroadcastManager $manager): JsonResponse
    {
        Gate::authorize('broadcasts.cancel');
        $user = request()->user() ?? throw new AuthenticationException;

        return ApiResponse::data(
            (new BroadcastResource($manager->cancel(BroadcastMessage::query()->findOrFail($broadcast), (int) $user->id)))->resolve(),
        );
    }

    public function deliveries(int $broadcast, DashboardBroadcastManager $manager): JsonResponse
    {
        Gate::authorize('broadcasts.view');

        return ApiResponse::data($manager->deliveries(BroadcastMessage::query()->findOrFail($broadcast)));
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
