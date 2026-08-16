<?php

namespace App\Actions\Notifications;

use App\Models\NotificationDelivery;
use App\Models\NotificationMessage;
use App\Models\NotificationPreference;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

class NotificationManager
{
    public function __construct(private readonly Outbox $outbox) {}

    /**
     * @param  list<int>  $recipientCentralUserIds
     * @param  array<string, mixed>  $data
     */
    public function create(string $type, string $title, ?string $body, array $recipientCentralUserIds, array $data = [], ?int $actorCentralUserId = null): NotificationMessage
    {
        return DB::connection('tenant')->transaction(function () use ($type, $title, $body, $recipientCentralUserIds, $data, $actorCentralUserId): NotificationMessage {
            $notification = NotificationMessage::query()->create([
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data === [] ? null : $data,
                'actor_central_user_id' => $actorCentralUserId,
            ]);

            $recipients = array_values(array_unique($recipientCentralUserIds));
            $pushDeliveryIds = [];

            foreach ($recipients as $recipientId) {
                NotificationDelivery::query()->create([
                    'notification_id' => $notification->id,
                    'central_user_id' => $recipientId,
                    'channel' => NotificationDelivery::CHANNEL_DATABASE,
                    'status' => NotificationDelivery::STATUS_DELIVERED,
                    'delivered_at' => now(),
                ]);

                if ($this->pushEnabled($recipientId, $type)) {
                    $pushDeliveryIds[] = NotificationDelivery::query()->create([
                        'notification_id' => $notification->id,
                        'central_user_id' => $recipientId,
                        'channel' => NotificationDelivery::CHANNEL_PUSH,
                        'status' => NotificationDelivery::STATUS_PENDING,
                    ])->id;
                }
            }

            if ($pushDeliveryIds !== []) {
                $this->outbox->publishAfterCommit('notification.push_requested', [
                    'notification_id' => (string) $notification->id,
                    'delivery_ids' => array_map('strval', $pushDeliveryIds),
                ]);
            }

            return $notification->load('deliveries');
        });
    }

    public function markRead(NotificationDelivery $delivery): NotificationDelivery
    {
        $delivery->forceFill([
            'status' => NotificationDelivery::STATUS_READ,
            'read_at' => now(),
        ])->save();

        return $delivery->refresh()->load('notification');
    }

    /** @return list<array<string, mixed>> */
    public function preferences(int $centralUserId): array
    {
        return NotificationPreference::query()
            ->where('central_user_id', $centralUserId)
            ->orderBy('type')
            ->orderBy('channel')
            ->get()
            ->map(fn (NotificationPreference $preference): array => [
                'id' => (string) $preference->id,
                'type' => $preference->type,
                'channel' => $preference->channel,
                'enabled' => (bool) $preference->enabled,
            ])
            ->all();
    }

    public function markAllRead(int $centralUserId): int
    {
        return NotificationDelivery::query()
            ->where('central_user_id', $centralUserId)
            ->where('channel', NotificationDelivery::CHANNEL_DATABASE)
            ->whereNull('read_at')
            ->update([
                'status' => NotificationDelivery::STATUS_READ,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function updatePreference(int $centralUserId, string $type, string $channel, bool $enabled): NotificationPreference
    {
        NotificationPreference::query()->updateOrCreate(
            [
                'central_user_id' => $centralUserId,
                'type' => $type,
                'channel' => $channel,
            ],
            ['enabled' => $enabled],
        );

        return NotificationPreference::query()
            ->where('central_user_id', $centralUserId)
            ->where('type', $type)
            ->where('channel', $channel)
            ->firstOrFail();
    }

    private function pushEnabled(int $centralUserId, string $type): bool
    {
        $preference = NotificationPreference::query()
            ->where('central_user_id', $centralUserId)
            ->where('type', $type)
            ->where('channel', NotificationDelivery::CHANNEL_PUSH)
            ->first();

        if ($preference === null) {
            return true;
        }

        return $preference->enabled;
    }
}
