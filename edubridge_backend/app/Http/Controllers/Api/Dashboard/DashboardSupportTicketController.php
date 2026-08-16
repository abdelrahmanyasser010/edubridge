<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Mobile\SupportTicketManager;
use App\Http\Requests\Dashboard\Support\DashboardSupportTicketFilterRequest;
use App\Http\Requests\Dashboard\Support\ReplyDashboardSupportTicketRequest;
use App\Http\Requests\Dashboard\Support\UpdateDashboardSupportTicketRequest;
use App\Http\Resources\Mobile\SupportTicketResource;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class DashboardSupportTicketController
{
    public function index(DashboardSupportTicketFilterRequest $request, SupportTicketManager $manager): JsonResponse
    {
        Gate::authorize('message.moderate');
        $paginator = $manager->adminList($request->validated());

        return ApiResponse::data(SupportTicketResource::collection($paginator->items())->resolve($request), meta: $this->paginationMeta($paginator));
    }

    public function show(Request $request, int $ticket, SupportTicketManager $manager): JsonResponse
    {
        Gate::authorize('message.moderate');

        return ApiResponse::data((new SupportTicketResource($manager->adminShow($ticket)))->resolve($request));
    }

    public function reply(ReplyDashboardSupportTicketRequest $request, int $ticket, SupportTicketManager $manager): JsonResponse
    {
        Gate::authorize('message.moderate');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new SupportTicketResource($manager->adminReply($ticket, (int) $user->id, (string) $request->validated('message'))))->resolve($request), Response::HTTP_CREATED);
    }

    public function update(UpdateDashboardSupportTicketRequest $request, int $ticket, SupportTicketManager $manager): JsonResponse
    {
        Gate::authorize('message.moderate');

        return ApiResponse::data((new SupportTicketResource($manager->adminUpdateStatus($ticket, (string) $request->validated('status'))))->resolve($request));
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return ['pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()]];
    }
}
