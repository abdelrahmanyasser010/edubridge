<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Calendar\DashboardCalendarManager;
use App\Http\Requests\Dashboard\Calendar\CalendarEventFilterRequest;
use App\Http\Requests\Dashboard\Calendar\StoreCalendarEventRequest;
use App\Http\Requests\Dashboard\Calendar\UpdateCalendarEventRequest;
use App\Http\Resources\Dashboard\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class CalendarEventController
{
    public function index(CalendarEventFilterRequest $request, DashboardCalendarManager $manager): JsonResponse
    {
        Gate::authorize('schedule.view');
        $events = $manager->events($request->validated());

        return ApiResponse::data(CalendarEventResource::collection($events->items())->resolve($request), meta: $this->paginationMeta($events));
    }

    public function store(StoreCalendarEventRequest $request, DashboardCalendarManager $manager): JsonResponse
    {
        Gate::authorize('schedule.manage');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new CalendarEventResource($manager->create($request->validated(), (int) $user->id)))->resolve($request), Response::HTTP_CREATED);
    }

    public function show(int $event, DashboardCalendarManager $manager): JsonResponse
    {
        Gate::authorize('schedule.view');

        return ApiResponse::data((new CalendarEventResource($manager->item(CalendarEvent::query()->findOrFail($event))))->resolve());
    }

    public function update(UpdateCalendarEventRequest $request, int $event, DashboardCalendarManager $manager): JsonResponse
    {
        Gate::authorize('schedule.manage');

        return ApiResponse::data((new CalendarEventResource($manager->update(CalendarEvent::query()->findOrFail($event), $request->validated())))->resolve($request));
    }

    public function destroy(int $event, DashboardCalendarManager $manager): JsonResponse
    {
        Gate::authorize('schedule.manage');

        return ApiResponse::data((new CalendarEventResource($manager->cancel(CalendarEvent::query()->findOrFail($event))))->resolve());
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
