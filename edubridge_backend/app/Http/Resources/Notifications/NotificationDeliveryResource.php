<?php

namespace App\Http\Resources\Notifications;

use App\Models\NotificationDelivery;
use App\Models\NotificationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class NotificationDeliveryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof NotificationDelivery) {
            throw new LogicException('NotificationDeliveryResource expects a NotificationDelivery model.');
        }

        $notification = $this->resource->notification;

        if (! $notification instanceof NotificationMessage) {
            throw new LogicException('NotificationDeliveryResource expects loaded notification relation.');
        }

        return [
            'id' => (string) $this->resource->id,
            'notification_id' => (string) $this->resource->notification_id,
            'channel' => $this->resource->channel,
            'status' => $this->resource->status,
            'delivered_at' => $this->resource->deliveredAtString(),
            'read_at' => $this->resource->readAtString(),
            'notification' => [
                'type' => $notification->type,
                'title' => $notification->title,
                'body' => $notification->body,
                'data' => $notification->data,
            ],
        ];
    }
}
