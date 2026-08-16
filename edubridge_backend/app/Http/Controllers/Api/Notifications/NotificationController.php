<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Actions\Notifications\NotificationManager;
use App\Http\Requests\Notifications\UpdateNotificationPreferenceRequest;
use App\Http\Resources\Notifications\NotificationDeliveryResource;
use App\Models\NotificationDelivery;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class NotificationController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', NotificationDelivery::class);
        $user = request()->user() ?? throw new AuthenticationException;

        return ApiResponse::data(NotificationDeliveryResource::collection(
            NotificationDelivery::query()
                ->with('notification')
                ->where('central_user_id', $user->id)
                ->where('channel', NotificationDelivery::CHANNEL_DATABASE)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
        )->resolve());
    }

    public function markRead(int $delivery, NotificationManager $manager): JsonResponse
    {
        $delivery = NotificationDelivery::query()->with('notification')->findOrFail($delivery);
        Gate::authorize('markRead', $delivery);

        return ApiResponse::data((new NotificationDeliveryResource($manager->markRead($delivery)))->resolve());
    }

    public function preferences(NotificationManager $manager): JsonResponse
    {
        $user = request()->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->preferences((int) $user->id));
    }

    public function markAllRead(NotificationManager $manager): JsonResponse
    {
        $user = request()->user() ?? throw new AuthenticationException;

        return ApiResponse::data(['updated' => $manager->markAllRead((int) $user->id)]);
    }

    public function updatePreference(UpdateNotificationPreferenceRequest $request, NotificationManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $preference = $manager->updatePreference(
            centralUserId: (int) $user->id,
            type: $request->validated('type'),
            channel: $request->validated('channel'),
            enabled: (bool) $request->validated('enabled'),
        );

        return ApiResponse::data([
            'id' => (string) $preference->id,
            'type' => $preference->type,
            'channel' => $preference->channel,
            'enabled' => $preference->enabled,
        ]);
    }
}
