<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Mobile\SupportTicketManager;
use App\Http\Requests\Mobile\CreateSupportTicketRequest;
use App\Http\Requests\Mobile\ReplySupportTicketRequest;
use App\Http\Requests\Mobile\SupportTicketFilterRequest;
use App\Http\Resources\Mobile\SupportTicketResource;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

class SupportTicketController
{
    public function categories(SupportTicketManager $manager): JsonResponse
    {
        return ApiResponse::data($manager->categories());
    }

    public function index(SupportTicketFilterRequest $request, SupportTicketManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $tickets = $manager->list((int) $user->id, (int) $request->validated('per_page', 20));

        return ApiResponse::data(
            SupportTicketResource::collection($tickets->items())->resolve($request),
            meta: $this->paginationMeta($tickets),
        );
    }

    public function store(CreateSupportTicketRequest $request, SupportTicketManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $data = $request->validated();
        $ticket = $manager->create((int) $user->id, $data['category_key'], $data['subject'], $data['message']);

        return ApiResponse::data((new SupportTicketResource($ticket))->resolve($request), Response::HTTP_CREATED);
    }

    public function show(Request $request, int $ticket, SupportTicketManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new SupportTicketResource($manager->show($ticket, (int) $user->id)))->resolve($request));
    }

    public function reply(ReplySupportTicketRequest $request, int $ticket, SupportTicketManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new SupportTicketResource(
            $manager->reply($ticket, (int) $user->id, (string) $request->validated('message')),
        ))->resolve($request), Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return ['pagination' => [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]];
    }
}
